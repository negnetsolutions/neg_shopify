<?php

namespace Drupal\neg_shopify\Form;

use Drupal\user\Form\UserPasswordForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\neg_shopify\Api\StoreFrontService;
use Drupal\neg_shopify\Api\GraphQlException;

/**
 * Provides a user password reset form.
 */
class ShopifyUserPassForm extends UserPasswordForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'user-pass';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['name']['#title'] = t('Email address');

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $account = $form_state->getValue('account');

    // If user is a Drupal user, use built in drupal reset system.
    if (!$account->hasRole('shopify_customer')) {
      return parent::submitForm($form, $form_state);
    }

    try {
      $this->requestShopifyPaswordReset($account->getEmail());
      $form_state->setRedirect('<front>');
    }
    catch (GraphQlException $e) {
      $errors = $e->getErrors();
      $errors = reset($errors);
      $this->messenger()->addError($errors['message']);
    }

  }

  /**
   * Requests a password reset from shopify.
   */
  protected function requestShopifyPaswordReset($email) {
    $query = <<<EOF
mutation customerRecover {
  customerRecover(email: "{$email}") {
    customerUserErrors {
      code
      field
      message
    }
  }
}
EOF;

    $results = StoreFrontService::request($query);

    if (isset($results['data']['customerRecover']['customerUserErrors']) && count($results['data']['customerRecover']['customerUserErrors']) > 0) {
      throw new GraphQlException($results['data']['customerRecover']['customerUserErrors'], $query);
    }

    $this->messenger()->addStatus('A message has been sent to your email with instructions to reset your password. Please check your email.');
  }

}
