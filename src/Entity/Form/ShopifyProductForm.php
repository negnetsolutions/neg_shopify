<?php

namespace Drupal\neg_shopify\Entity\Form;

use Drupal\neg_shopify\Settings;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Url;

/**
 * Form controller for Shopify product edit forms.
 *
 * @ingroup shopify
 */
class ShopifyProductForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /* @var $entity \Drupal\shopify\Entity\ShopifyProduct */
    $form = parent::buildForm($form, $form_state);
    $entity = $this->entity;

    $form['langcode'] = [
      '#title' => $this->t('Language'),
      '#type' => 'language_select',
      '#default_value' => $entity->langcode->value,
      '#languages' => Language::STATE_ALL,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array $form, FormStateInterface $form_state) {
    // Build the entity object from the submitted values.
    $entity = parent::submit($form, $form_state);

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    $status = $entity->save();
    $messenger = \Drupal::messenger();

    switch ($status) {
      case SAVED_NEW:
        $messenger->addStatus($this->t('Created the %label Shopify product.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        $messenger->addStatus($this->t('Saved the %label Shopify product.', [
          '%label' => $entity->label(),
        ]));
    }
    $form_state->setRedirect('entity.shopify_product.edit_form', ['shopify_product' => $entity->id()]);
  }

}
