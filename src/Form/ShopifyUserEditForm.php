<?php

namespace Drupal\neg_shopify\Form;

use Drupal\Core\Link;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\user\ProfileForm;
use Drupal\neg_shopify\Api\StoreFrontService;
use Drupal\neg_shopify\Settings;
use Drupal\neg_shopify\UserManagement;
use Drupal\neg_shopify\ShopifyCustomer;


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
      '#description' => $this->t('A valid email address. All emails from the system will be sent to this address. The email address is not made public and will only be used if you wish to receive a new password or wish to receive certain news or notifications by email.'),
      '#required' => TRUE,
      '#default_value' => $account->getEmail(),
    ];

    $form['account']['pass'] = [
      '#type' => 'password_confirm',
      '#size' => 25,
      '#description' => $this->t('To change the current user password, enter the new password in both fields.'),
      '#access' => ($user->id() === $account->id()) ? TRUE : FALSE,
    ];

    $roles = array_map(['\Drupal\Component\Utility\Html', 'escape'], user_role_names(TRUE));
    $form['account']['roles'] = [
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
    ];

    if ($password !== NULL) {
      $input['password'] = $form_state->getValue('pass');
    }

    if ($this->entity === $this->currentUser()) {
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
    catch (\Exception $e) {
      $form_state->setErrorByName('field_first_name', $e->getMessage());
      return;
    }

    if ($ret) {
      // Update Customer locally.
      $account = $this->entity;
      $account->set('field_first_name', $form_state->getValue('field_first_name'));
      $account->set('field_last_name', $form_state->getValue('field_last_name'));
      $account->set('mail', $form_state->getValue('mail'));
      $account->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitShopifyUserForm(array &$form, FormStateInterface $form_state) {
    \Drupal::messenger()->addStatus('Account Settings Successfully Updated!', TRUE);

    $form_state->setRedirect(
      'entity.user.canonical',
      ['user' => $this->entity->id()]
    );
  }

}
