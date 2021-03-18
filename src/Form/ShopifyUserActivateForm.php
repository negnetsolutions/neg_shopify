<?php

namespace Drupal\neg_shopify\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\neg_shopify\Api\StoreFrontService;
use Drupal\neg_shopify\Api\GraphQlException;
use Drupal\neg_shopify\UserManagement;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\user\UserStorageInterface;
use Drupal\neg_shopify\Settings;

/**
 * Form handler for the user activation forms.
 *
 * @internal
 */
class ShopifyUserActivateForm extends FormBase {

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
    return 'shopify_register_user_activation_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ShopifyProduct $product = NULL) {
    $activateUrl = \Drupal::request()->query->get('activation_url');
    if ($activateUrl === NULL) {
      throw new NotFoundHttpException();
    }

    $form['top'] = [
      '#markup' => '<h1>Activate Account</h1><p>Create your password to activate your account.</p>'
    ];

    $form['pass'] = [
      '#type' => 'password',
      '#size' => 25,
      '#required' => TRUE,
      '#attributes' => [
        'placeholder' => 'Password',
      ],
    ];

    $form['confirm'] = [
      '#type' => 'password',
      '#size' => 25,
      '#required' => TRUE,
      '#attributes' => [
        'placeholder' => 'Confirm Password',
      ],
    ];

    $form['url'] = [
      '#type' => 'hidden',
      '#value' => $activateUrl,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Activate Account'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $password = $form_state->getValue('pass');
    $confirm = $form_state->getValue('confirm');
    $url = $form_state->getValue('url');

    if ($password !== $confirm) {
      $form_state->setErrorByName('confirm', $this->t('Passwords do not match!', []));
      return;
    }

    $query = <<<EOF
mutation activateCustomer {
  customerActivateByUrl(activationUrl: "{$url}", password: "{$password}") {
    customer {
      id
      email
      firstName
      lastName
    }
    customerAccessToken {
      accessToken
      expiresAt
    }
    customerUserErrors {
      code
      field
      message
    }
  }
}
EOF;

    try {
      $results = StoreFrontService::request($query);
      Settings::log('RESTULS: %r', [
        '%r' => print_r($results, TRUE),
      ]);

      // User is logged in.
      if (!isset($results['data']['customerActivateByUrl']['customer']['email'])) {
        throw new GraphQlException($results['data']['customerActivateByUrl']['customerUserErrors'], $query);
      }

      if (!isset($results['data']['customerActivateByUrl']['customerAccessToken']['accessToken'])) {
        throw new GraphQlException($results['data']['customerActivateByUrl']['customerUserErrors'], $query);
      }

      // Get user email.
      $email = $results['data']['customerActivateByUrl']['customer']['email'];
      $accessTokenData = $results['data']['customerActivateByUrl']['customerAccessToken'];

      // Find Drupal User.
      $drupalUser = UserManagement::loadUserByMail($email);

      if (!$drupalUser) {
        // Create a new user.
        $drupalUser = UserManagement::provisionDrupalUser($email);

        $id = base64_decode($results['data']['customerActivateByUrl']['customer']['id']);
        $id = str_replace('gid://shopify/Customer/', '', $id);
        $shopifyUser = [
          'id' => $id,
          'first_name' => $results['data']['customerActivateByUrl']['customer']['firstName'],
          'last_name' => $results['data']['customerActivateByUrl']['customer']['lastName'],
          'email' => $results['data']['customerActivateByUrl']['customer']['email'],
        ];

        // Sync user with data from login.
        UserManagement::syncUserWithShopify($shopifyUser, $drupalUser);
      }

      // Login the user and set shopify state variables.
      UserManagement::setShopifyUserState($drupalUser, $accessTokenData);
      $form_state->set('uid', $drupalUser->id());
    }
    catch (GraphQlException $e) {
      $errors = $e->getErrors();
      $errors = reset($errors);

      $form_state->setErrorByName('pass', $this->t('%m', [
        '%m' => $errors['message'],
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    \kint($form_state);

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
