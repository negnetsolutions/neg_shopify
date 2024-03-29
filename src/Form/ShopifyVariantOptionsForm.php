<?php

namespace Drupal\neg_shopify\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\neg_shopify\Entity\ShopifyProduct;
use Drupal\neg_shopify\Entity\ShopifyProductVariant;
use Drupal\Core\Cache\CacheableRedirectResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Url;

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

    // Set form action to product url.
    $form['#action'] = $product->toUrl()->toString();

    $form_state->set('product', $product);

    $variant_id = \Drupal::request()->get('variant_id', FALSE);

    // Load options.
    $options = [];
    $optionsAttributes = [];
    $variants = $product->get('variants');

    if ($variant_id === FALSE) {
      $variant_id = $this->getFirstVariantId($variants);
      $_GET['variant_id'] = $variant_id;
    }

    foreach ($variants as $i => $variant) {

      $attributes = [];

      if (!$variant->entity->isAvailable()) {
        $attributes['disabled'] = 'disabled';
      }

      $variantOptions = $variant->entity->getFormattedOptions();
      $label = implode(' / ', $variantOptions);
      if ($label === 'Default Title') {
        break;
      }

      $key = $variant->entity->get('variant_id')->value;
      $options[$key] = $label;
      $optionsAttributes[$key] = $attributes;
    }

    // Check to see if variant_id is false and this is the first index.
    if ($variant_id === FALSE) {
      $keys = array_keys($optionsAttributes);
      if (count($keys) > 0 && isset($optionsAttributes[$keys[0]]['disabled'])) {
        // The default attribute is disabled. We need to try to
        // redirect to an available product.
        foreach ($optionsAttributes as $key => $attributes) {
          if (!isset($attributes['disabled'])) {
            return $this->goToVariantNow($key, $form_state);
          }
        }
      }
    }

    if (count($options) === 0) {
      $form['options'] = [
        '#type' => 'hidden',
        '#default_value' => ($variant_id) ? $variant_id : '',
      ];
    }
    else {
      $form['options'] = [
        '#type' => 'select',
        '#options' => $options,
        '#options_attributes' => $optionsAttributes,
        '#default_value' => ($variant_id) ? $variant_id : array_keys($options)[0],
        '#attributes' => ['aria-label' => 'Select style', 'onchange' => 'javascript:this.form.update_variant.click();'],
      ];

      $form['update_variant'] = [
        '#type' => 'submit',
        '#value' => t('Update'),
        '#name' => 'update_variant',
        '#attributes' => ['style' => 'display:none;'],
      ];

    }

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
   * Gets first variant id.
   */
  private function getFirstVariantId(object $variants) {
    foreach ($variants as $i => $variant) {
      if ($variant->entity->isAvailable()) {
        $key = $variant->entity->get('variant_id')->value;
        return $key;
      }
    }

    return FALSE;
  }

  /**
   * Redirects to variant page immediately.
   */
  private function goToVariantNow($variant_id, FormStateInterface $form_state) {

    // We found an available product.
    $query = [
      'variant_id' => $variant_id,
    ];

    $src = Url::fromRoute('entity.shopify_product.canonical', [
      'shopify_product' => $form_state->get('product')->id(),
    ], [
      'query' => $query,
    ])->toString();

    $redirect = new CacheableRedirectResponse($src, 302);

    $build = [
      '#cache' => [
        'contexts' => ['url.query_args'],
        'tags' => $form_state->get('product')->getCacheTags(),
      ],
    ];
    $redirect->addCacheableDependency(CacheableMetadata::createFromRenderArray($build));
    $redirect->setEtag($src);
    $redirect->send();
    exit;
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
