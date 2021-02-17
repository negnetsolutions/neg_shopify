<?php

namespace Drupal\neg_shopify\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\neg_shopify\StoreFrontService;
use Drupal\neg_shopify\UserManagement;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\user\UserStorageInterface;

/**
 * Form handler for the user register forms.
 *
 * @internal
 */
class ShopifyRegisterForm extends FormBase {

  /**
   * The Drupal user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $drupalUser;

  /**
   * User Storage Service.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  /**
   * Constructs a new UserLoginForm.
   *
   * @param \Drupal\user\UserStorageInterface $user_storage
   *   The user storage.
   */
  public function __construct(UserStorageInterface $user_storage) {
    $this->userStorage = $user_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'shopify_register_user_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ShopifyProduct $product = NULL) {
    $form['firstName'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First Name'),
      '#required' => TRUE,
      '#default_value' => '',
    ];

    $form['lastName'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last Name'),
      '#required' => TRUE,
      '#default_value' => '',
    ];

    $form['mail'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
      '#default_value' => '',
    ];

    $form['pass'] = [
      '#title' => $this->t('Password'),
      '#type' => 'password',
      '#size' => 25,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Create Account'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $firstName = $form_state->getValue('firstName');
    $lastName = $form_state->getValue('lastName');
    $mail = $form_state->getValue('mail');
    $password = $form_state->getValue('pass');

    $this->drupalUser = UserManagement::loadUserByMail($mail);

    if ($this->drupalUser) {
      if (UserManagement::verifyUserAllowed($this->drupalUser) === FALSE) {
        $form_state->setErrorByName('mail', $this->t('This user is not allowed to register!', []));
        return;
      }
    }
    else {
      if (UserManagement::verifyAccountCreation() === FALSE) {
        $form_state->setErrorByName('mail', $this->t('User registration is closed!', []));
        return;
      }
    }

    $query = <<<EOF
mutation customerCreate {
  customerCreate(input: { email: "{$mail}", password: "{$password}", firstName: "{$firstName}", lastName: "{$lastName}"}) {
    customer {
      id
    }
    customerUserErrors {
      code
      field
      message
    }
  },
  customerAccessTokenCreate(input: { email: "{$mail}", password: "{$password}" }) {
    customerUserErrors {
      code
      field
      message
    }
    customerAccessToken {
      accessToken
      expiresAt
    }
  }
}
EOF;

    try {
      $results = StoreFrontService::request($query);

      // User is logged in.
      if ($results['data']['customerAccessTokenCreate']['customerAccessToken'] !== NULL) {
        // User is logged in. Go with it!.
        $accessTokenData = $results['data']['customerAccessTokenCreate']['customerAccessToken'];

        if (!$this->drupalUser) {
          // Create a new user.
          $this->drupalUser = UserManagement::provisionDrupalUser($mail);
        }

        if ($this->drupalUser) {
          // Login the user and set shopify state variables.
          UserManagement::setShopifyUserState($this->drupalUser, $accessTokenData);
          $form_state->set('uid', $this->drupalUser->id());
        }

      }
      else {
        // Login failed!
        if (isset($results['data']['customerCreate']['customerUserErrors']) && $results['data']['customerCreate']['customerUserErrors'] !== NULL) {
          $form_state->setErrorByName('mail', $this->t('A user already exists with this email address. Please try logging in or resetting your password.', []));
        }
      }

    }
    catch (\Exception $e) {
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if (empty($uid = $form_state->get('uid'))) {
      return;
    }

    $account = $this->userStorage->load($uid);

    // A destination was set, probably on an exception controller.
    if (!$this->getRequest()->request->has('destination')) {
      $form_state->setRedirect(
        'entity.user.canonical',
        ['user' => $account->id()]
      );
    }
    else {
      $this->getRequest()->query->set('destination', $this->getRequest()->request->get('destination'));
    }

    user_login_finalize($account);
  }

}
