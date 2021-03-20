<?php

namespace Drupal\neg_shopify\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\user\ProfileForm;
use Drupal\neg_shopify\Settings;
use Drupal\neg_shopify\UserManagement;
use Drupal\neg_shopify\ShopifyCustomer;
use Drupal\negnet_utility\FieldUtilities;
use Drupal\neg_shopify\Api\GraphQlException;

/**
 * Provides a user password reset form.
 */
class ShopifyUserEditForm extends ProfileForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    if ($this->isShopifyUser()) {
      // Display Shopify User Form.
      $form = $this->buildShopifyUserForm($form, $form_state);
    }
    else {
      // Display default form.
      $form = parent::buildForm($form, $form_state);
    }

    $form['#cache']['tags'][] = 'config:neg_shopify.settings';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($this->isShopifyUser()) {
      // Display Shopify User Form.
      return;
    }
    else {
      parent::validateForm($form, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isShopifyUser() {
    $allowShopifyLogins = (BOOL) Settings::config()->get('allow_shopify_users');
    if ($allowShopifyLogins === TRUE) {
      if (UserManagement::verifyUserAllowed($this->entity)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Builds shopify user form.
   */
  protected function buildShopifyUserForm(array $form, FormStateInterface $form_state) {
    $account = $this->entity;
    $user = $this->currentUser();
    $customer = new ShopifyCustomer([
      'user' => $account,
    ]);

    $details = $customer->getCustomerDetails();

    $form['account']['field_first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First Name'),
      '#required' => TRUE,
      '#default_value' => $account->get('field_first_name')->value,
    ];

    $form['account']['field_last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last Name'),
      '#required' => TRUE,
      '#default_value' => $account->get('field_last_name')->value,
    ];

    $form['account']['mail'] = [
      '#type' => 'email',
      '#title' => $this->t('Email address'),
      '#required' => TRUE,
      '#default_value' => $account->getEmail(),
    ];

    $form['account']['phone'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Phone'),
      '#default_value' => FieldUtilities::formatPhoneNumber($details['phone']),
    ];

    $form['account']['accepts_marketing'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Subscribed to Marketing Emails?'),
      '#default_value' => $details['accepts_marketing'],
    ];

    $form['pass'] = [
      '#title' => $this->t('Password'),
      '#type' => 'password',
      '#size' => 25,
      '#description' => t('Enter a new password to change your password.'),
      '#access' => ($user->id() === $account->id()) ? TRUE : FALSE,
      '#attributes' => [
        'placeholder' => 'Password',
      ],
    ];

    $form['confirm'] = [
      '#title' => $this->t('Confirm Password'),
      '#type' => 'password',
      '#size' => 25,
      '#access' => ($user->id() === $account->id()) ? TRUE : FALSE,
      '#attributes' => [
        'placeholder' => 'Confirm Password',
      ],
    ];

    $roles = array_map(['\Drupal\Component\Utility\Html', 'escape'], user_role_names(TRUE));
    $form['roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Roles'),
      '#default_value' => $account->getRoles(),
      '#options' => $roles,
      '#access' => $roles && $user->hasPermission('administer permissions'),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Update'),
      '#submit' => ['::submitShopifyUserForm'],
    ];

    $form['#validate'] = [
      '::saveShopifyData',
    ];

    $form['#cache']['tags'][] = 'config:neg_shopify.settings';
    return $form;
  }

  /**
   * Sets an error if supplied username has been blocked.
   */
  public function saveShopifyData(array &$form, FormStateInterface $form_state) {
    $input = [
      'email' => $form_state->getValue('mail'),
      'firstName' => $form_state->getValue('field_first_name'),
      'lastName' => $form_state->getValue('field_last_name'),
      'acceptsMarketing' => ($form_state->getValue('accepts_marketing') == 1) ? TRUE : FALSE,
      'phone' => preg_replace('/[^0-9\+]/', '', $form_state->getValue('phone')),
    ];

    if ($form_state->hasValue('pass') && strlen($form_state->getValue('pass')) > 0) {
      $input['password'] = $form_state->getValue('pass');
      $confirm = $form_state->getValue('confirm');

      if ($input['password'] !== $confirm) {
        $form_state->setErrorByName('pass', 'Passwords do not match.');
      }
    }

    if ($this->entity->id() === $this->currentUser()->id()) {
      $accessToken = UserManagement::getAccessToken($this->entity->id());

      if ($accessToken === NULL) {
        $form_state->setErrorByName('field_first_name', 'User Access Token Missing. Please log out and log back in!');
        return;
      }

      $accessParams = [
        'accessToken' => $accessToken,
      ];
    }
    else {
      $accessParams = [
        'user' => $this->entity,
      ];
    }

    $customer = new ShopifyCustomer($accessParams);

    try {
      $ret = $customer->updateShopifyUser($input);
    }
    catch (GraphQlException $e) {
      $errors = $e->getErrors();
      $errors = reset($errors);
      $msg = $errors['message'];

      $form_state->setErrorByName('field_first_name', $this->t('%m', [
        '%m' => $errors['message'],
      ]));

      return;
    }

    if ($ret) {
      // Update Customer locally.
      $account = $this->entity;
      $account->set('field_first_name', $form_state->getValue('field_first_name'));
      $account->set('field_last_name', $form_state->getValue('field_last_name'));
      $account->set('mail', $form_state->getValue('mail'));
      $account->save();

      // Clear shopify details to force a re-load.
      UserManagement::clearShopifyUserDetailsState($account);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitShopifyUserForm(array &$form, FormStateInterface $form_state) {
    \Drupal::messenger()->addStatus('Account Settings Successfully Updated!', TRUE);

    if ($form_state->hasValue('roles')) {
      $user = $this->getEntity($form_state);
      $roles = $form_state->getValue('roles');
      $roles = array_keys(array_filter($roles));
      $user->set('roles', $roles);
      $user->save();
    }

    $form_state->setRedirect(
      'entity.user.canonical',
      ['user' => $this->entity->id()]
    );
  }

}
