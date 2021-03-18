<?php

namespace Drupal\neg_shopify\Form;

use Drupal\Core\Link;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\user\Form\UserLoginForm;
use Drupal\neg_shopify\Api\StoreFrontService;
use Drupal\neg_shopify\Api\GraphQlException;
use Drupal\neg_shopify\UserManagement;
use Drupal\neg_shopify\ShopifyCustomer;

use Drupal\user\UserStorageInterface;
use Drupal\user\UserFloodControlInterface;
use Drupal\user\UserAuthInterface;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Provides a user password reset form.
 */
class ShopifyLoginForm extends UserLoginForm {

  /**
   * Access Token from Shopify.
   *
   * @var array
   */
  protected $accessToken;

  /**
   * Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The password.
   *
   * @var string
   */
  protected $userPass;

  /**
   * The username.
   *
   * @var string
   */
  protected $userName;

  /**
   * The Drupal user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $drupalUser;

  /**
   * Constructs a new UserLoginForm.
   *
   * @param \Drupal\user\UserFloodControlInterface $user_flood_control
   *   The user flood control service.
   * @param \Drupal\user\UserStorageInterface $user_storage
   *   The user storage.
   * @param \Drupal\user\UserAuthInterface $user_auth
   *   The user authentication object.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   */
  public function __construct($user_flood_control, UserStorageInterface $user_storage, UserAuthInterface $user_auth, RendererInterface $renderer, EntityTypeManagerInterface $entity_type_manager) {
    if (!$user_flood_control instanceof UserFloodControlInterface) {
      @trigger_error('Passing the flood service to ' . __METHOD__ . ' is deprecated in drupal:9.1.0 and is replaced by user.flood_control in drupal:10.0.0. See https://www.drupal.org/node/3067148', E_USER_DEPRECATED);
      $user_flood_control = \Drupal::service('user.flood_control');
    }
    $this->userFloodControl = $user_flood_control;
    $this->userStorage = $user_storage;
    $this->userAuth = $user_auth;
    $this->renderer = $renderer;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('user.flood_control'),
      $container->get('entity_type.manager')->getStorage('user'),
      $container->get('user.auth'),
      $container->get('renderer'),
      $container->get('entity_type.manager'),
    );
  }


  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'shopify_login_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $form['#validate'] = [
      '::validateName',
      '::validateAuthentication',
      '::validateShopifyAuthentication',
      '::validateFinal',
    ];

    return $form;
  }

  /**
   * Determine if the corresponding Drupal account exists and is mapped.
   *
   * Ideally we would only ask the external authmap but are allowing matching
   * by name, too, for association handling later.
   */
  protected function initializeDrupalUserFromAuthName(): void {
    $this->drupalUser = UserManagement::loadUserByMail($this->userName);
  }

  /**
   * Validate common login constraints for user.
   *
   * @return bool
   *   Continue authentication.
   */
  protected function validateCommonLoginConstraints(): bool {

    $this->initializeDrupalUserFromAuthName();

    if ($this->drupalUser) {
      $result = UserManagement::verifyUserAllowed($this->drupalUser);
    }
    else {
      $result = UserManagement::verifyAccountCreation();
    }
    return $result;
  }


  /**
   * Sets an error if supplied username has been blocked.
   */
  public function validateName(array &$form, FormStateInterface $form_state) {
    if (!$form_state->isValueEmpty('name') && user_is_blocked($form_state->getValue('name'))) {
      // Blocked in user administration.
      $form_state->setErrorByName('name', $this->t('The username %name has not been activated or is blocked.', ['%name' => $form_state->getValue('name')]));
    }
  }

  /**
   * Sets an error if supplied username has been blocked.
   */
  public function validateShopifyAuthentication(array &$form, FormStateInterface $form_state) {

    // Check to see if Drupal already authorized user.
    if ($form_state->get('uid')) {
      return;
    }

    $username = $form_state->getValue('name');
    $this->userName = $username;

    // Basic Validations.
    if (!$this->validateCommonLoginConstraints()) {
      return;
    }

    $password = trim($form_state->getValue('pass'));
    $this->userPass = $password;

    try {
      if (!$this->authenticateShopifyUser()) {
        return;
      }
    }
    catch (GraphQlException $e) {
      $errors = $e->getErrors();
      $errors = reset($errors);
      $msg = $errors['message'];

      $form_state->setErrorByName('name', $this->t('Login Error: %m', [
        '%m' => $msg,
      ]));

      return;
    }

    // Query for customer information.
    if (!$this->drupalUser) {
      // Create a new user.
      $this->drupalUser = UserManagement::provisionDrupalUser($this->userName);

      $customer = new ShopifyCustomer([
        'accessToken' => $this->accessToken['accessToken'],
      ]);

      $customer->updateDrupalUser($this->drupalUser);
    }

    // All passed, log the user in by handing over the UID.
    if ($this->drupalUser) {
      // Login the user and set shopify state variables.
      UserManagement::setShopifyUserState($this->drupalUser, $this->accessToken);
      $form_state->set('uid', $this->drupalUser->id());
    }

  }

  /**
   * Authenticates user against shopify.
   */
  protected function authenticateShopifyUser() {
    $username = $this->userName;
    $password = $this->userPass;
    $query = <<<EOF
mutation customerAccessTokenCreate {
  customerAccessTokenCreate(input: { email: "{$username}", password: "{$password}" }) {
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

    $results = StoreFrontService::request($query);

    if (isset($results['data']['customerAccessTokenCreate']['customerAccessToken']) && $results['data']['customerAccessTokenCreate']['customerAccessToken'] !== NULL) {
      // Successful Shopify Login.
      $this->accessToken = $results['data']['customerAccessTokenCreate']['customerAccessToken'];

      return TRUE;
    }

    if (isset($results['data']['customerAccessTokenCreate']['customerUserErrors'])) {
      throw new GraphQlException($results['data']['customerAccessTokenCreate']['customerUserErrors'], $query);
    }

    return FALSE;
  }

}
