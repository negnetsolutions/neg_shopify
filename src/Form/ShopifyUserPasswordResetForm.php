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
class ShopifyUserPasswordResetForm extends FormBase {

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
    return 'shopify_password_reset_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ShopifyProduct $product = NULL) {
    $resetUrl = \Drupal::request()->query->get('reset_url');
    if ($resetUrl === NULL) {
      throw new NotFoundHttpException();
    }

    $form['top'] = [
      '#markup' => '<h1>Reset Password</h1><p>Enter a password to access your account.</p>'
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
      '#value' => $resetUrl,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Reset Password'),
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

    // Atempt to reset the password.
    $query = <<<EOF
mutation customerResetByUrl {
  customerResetByUrl(resetUrl: "{$url}", password: "{$password}") {
    customer { id }
    customerUserErrors { code field message}
  }
}
EOF;

    try {
      $results = StoreFrontService::request($query);

      // User is logged in.
      if (!isset($results['data']['customerResetByUrl']['customer']['id'])) {
        throw new GraphQlException($results['data']['customerResetByUrl']['customerUserErrors'], $query);
      }
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
    $this->messenger()->addStatus('Your password has been reset. Please login.');
    $form_state->setRedirect('user.login');
  }

}
