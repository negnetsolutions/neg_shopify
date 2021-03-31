<?php

namespace Drupal\neg_shopify\Entity\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\Language;

/**
 * Form controller for Shopify vendor edit forms.
 *
 * @ingroup shopify
 */
class ShopifyVendorForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /* @var $entity \Drupal\shopify\Entity\Shopifyvendor */
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
        $messenger->addStatus($this->t('Created the %label Shopify vendor.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        $messenger->addStatus($this->t('Saved the %label Shopify vendor.', [
          '%label' => $entity->label(),
        ]));
    }
    $form_state->setRedirect('entity.shopify_vendor.canonical', ['shopify_vendor' => $entity->id()]);
  }

}
