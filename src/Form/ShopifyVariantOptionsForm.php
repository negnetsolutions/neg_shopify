<?php

namespace Drupal\neg_shopify\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\neg_shopify\Entity\ShopifyProduct;
use Drupal\neg_shopify\Entity\ShopifyProductVariant;
use Drupal\neg_shopify\Settings;

/**
 * Class ShopifyVariantOptionsForm.
 *
 * @package Drupal\shopify\Form
 */
class ShopifyVariantOptionsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'shopify_variant_options_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ShopifyProduct $product = NULL) {

    // Set cache contexts and tags.
    $form['#cache']['contexts'] = ['url.query_args'];
    $form['#cache']['tags'] = $product->getCacheTags();

    $form_state->set('product', $product);

    $variant_id = \Drupal::request()->get('variant_id', FALSE);

    // Load options.
    $options = [];
    $variants = $product->get('variants');

    foreach ($variants as $i => $variant) {
      if ($variant->entity->isAvailable()) {
        $variantOptions = $variant->entity->getFormattedOptions();
        $label = implode(' / ', $variantOptions);
        $options[$variant->entity->get('variant_id')->value] = $label;
      }
    }

    $form['options'] = [
      '#type' => 'select',
      '#options' => $options,
      '#default_value' => ($variant_id) ? $variant_id : '',
      '#attributes' => ['onchange' => 'javascript:this.form.update_variant.click();'],
    ];

    $form['update_variant'] = [
      '#type' => 'submit',
      '#value' => t('Update'),
      '#name' => 'update_variant',
      '#attributes' => ['style' => 'display:none;'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->goToVariant($form_state->getValue('options'), $form_state);
  }

  /**
   * Redirects the page to the product with a variant selected.
   *
   * @param array $options
   *   Options from the form_state.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   *
   *   TODO: Move $options to the end.
   */
  private function goToVariant($variant_id, FormStateInterface $form_state) {

    $variant = ShopifyProductVariant::loadByVariantId($variant_id);
    $query = [];

    if ($variant instanceof ShopifyProductVariant) {
      // We have a matching variant we can redirect to.
      $query = [
        'variant_id' => $variant_id,
      ];
    }

    // Redirect.
    $form_state->setRedirect('entity.shopify_product.canonical', [
      'shopify_product' => $form_state->get('product')
        ->id(),
    ], [
      'query' => $query,
    ]);
  }

}
