<?php

namespace Drupal\neg_shopify\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\neg_shopify\Entity\ShopifyProduct;
use Drupal\neg_shopify\Entity\ShopifyProductVariant;
use Drupal\neg_shopify\Settings;

/**
 * Class ShopifyAddToCartForm.
 *
 * @package Drupal\shopify\Form
 */
class ShopifyAddToCartForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'shopify_add_to_cart_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ShopifyProduct $product = NULL) {
    // Disable caching of this form.
    $form['#cache']['max-age'] = 0;

    $form_state->set('product', $product);

    $variant_id = (isset($_GET['variant_id']) && is_numeric($_GET['variant_id'])) ? $_GET['variant_id'] : FALSE;

    $variant = NULL;
    if ($variant_id !== FALSE) {
      $variant = ShopifyProductVariant::loadByVariantId($variant_id);
    }

    if (!$variant) {
      // No variant set yet, setup the default first variant.
      $entity_id = $product->variants->get(0)->getValue()['target_id'];
      $variant = ShopifyProductVariant::load($entity_id);
      $variant_id = $variant->variant_id->value;
    }

    if (!$variant) {
      return [];
    }

    $form['#action'] = '//' . Settings::shopInfo('domain') . '/cart/add';

    // Attach the cart library.
    Settings::attachShopifyJs($form);

    // Data attribute used by shopify.js.
    $form['#attributes']['data-variant-id'] = $variant_id;
    $form['#attributes']['data-product-id'] = $product->get('product_id')->value;
    $form['#attributes']['data-variant-sku'] = $variant->get('sku')->value;
    $form['#attributes']['data-variant-price'] = $variant->get('price')->value;

    // Variant ID to add to the Shopify cart.
    $form['id'] = [
      '#type' => 'hidden',
      '#value' => $variant_id,
    ];

    // Send user back to the site.
    $form['return_to'] = [
      '#type' => 'hidden',
      '#value' => 'back',
    ];

    $published_at = $product->get('published_at')->value;
    $published = ($published_at !== NULL && time() > $published_at);

    if ($published) {
      // Send the quantity value.
      if ($product->get('is_available')->value != FALSE) {
        $form['quantity'] = [
          '#type' => 'number',
          '#title' => t('Quantity'),
          '#default_value' => 1,
          '#attributes' => ['min' => 0, 'max' => 999],
        ];
      }

      if (empty($variant_id)) {
        // No variant matches these options.
        $form['submit'] = [
          '#type' => 'button',
          '#disabled' => TRUE,
          '#value' => t('Unavailable'),
          '#name' => 'add_to_cart',
        ];
      }
      else {
        if ($variant->inventory_policy->value == 'continue' || $variant->inventory_quantity->value > 0 || empty($variant->inventory_management->value)) {
          // User can add this variant to their cart.
          $form['submit'] = [
            '#type' => 'submit',
            '#value' => t('Add to cart'),
            '#name' => 'add_to_cart',
            '#attributes' => [
              'onclick' => [
                'return shopping_cart.addToCart(this);',
              ],
            ],
          ];
        }
        elseif ($product->get('is_preorder')->value == 1) {

          $config = Settings::config();

          // This variant is out of stock.
          $form['submit'] = [
            '#type' => 'markup',
            '#markup' => t(($config->get('presale_text') !== NULL) ? $config->get('presale_text') : '<p>Available for preorder</p>'),
            '#cache' => [
              'tags' => ['config:neg_shopify.settings', 'shopify_product:' . $product->id()],
            ]
          ];
        }
        else {
          // This variant is out of stock.
          $form['submit'] = [
            '#type' => 'submit',
            '#disabled' => TRUE,
            '#value' => t('Out of stock'),
            '#name' => 'add_to_cart',
          ];
        }
      }
    }
    else {
      // This variant is out of stock.
      $form['submit'] = [
        '#type' => 'submit',
        '#disabled' => TRUE,
        '#value' => t('Unavailable'),
        '#name' => 'add_to_cart',
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
  }

}
