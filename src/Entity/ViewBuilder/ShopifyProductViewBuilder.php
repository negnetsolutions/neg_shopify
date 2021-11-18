<?php

namespace Drupal\neg_shopify\Entity\ViewBuilder;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\neg_shopify\Entity\ShopifyProductVariant;

/**
 * Class View Builder for Shopify Products.
 */
class ShopifyProductViewBuilder extends EntityViewBuilder {

  /**
   * {@inheritdoc}
   */
  protected function alterBuild(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, $view_mode) {

    if (isset($build['body_html'])) {
      $build['body_html'][0]['#format'] = 'html';
    }

    if ($display->getComponent('related_items')) {
      $items = $entity->renderRelatedItems();
      if (count($items) > 0) {
        $build['related_items'] = [
          '#theme' => 'shopify_related_items',
          '#items' => $items,
        ];
      }
    }

    if ($display->getComponent('active_variant')) {
      $active_variant = NULL;

      if ($variant_id = \Drupal::request()->get('variant_id')) {
        $active_variant = ShopifyProductVariant::loadByVariantId($variant_id);
      }
      else {
        $variants = $entity->variants;
        $variant_id = $this->getFirstVariantId($variants);
        if ($variant_id !== FALSE) {
          $active_variant = ShopifyProductVariant::load($variant_id);
        }
      }

      // Display the active variant.
      if ($active_variant instanceof ShopifyProductVariant) {
        $build['active_variant'] = [
          '#prefix' => '<div class="product-active-variant variant-display variant-display--view-' . $view_mode . '">',
          'variant' => \Drupal::entityTypeManager()
            ->getViewBuilder('shopify_product_variant')
            ->view($active_variant, $view_mode),
          '#suffix' => '</div>',
        ];
      }

    }

    $form = $display->getComponent('add_to_cart_form');
    if ($form) {

      if (in_array($build['#view_mode'], ['full', 'store_listing'])) {
        if ($entity->get('is_available')->value != FALSE) {
          // Need to display the add to cart form.
          $build['add_to_cart_form']['variant_options'] = \Drupal::formBuilder()
            ->getForm('Drupal\neg_shopify\Form\ShopifyVariantOptionsForm', $entity);
        }

        $build['add_to_cart_form']['add_to_cart'] = \Drupal::formBuilder()
          ->getForm('Drupal\neg_shopify\Form\ShopifyAddToCartForm', $entity);

        $build['add_to_cart_form']['#weight'] = $form['weight'];
      }
    }
  }

  /**
   * Gets first variant id.
   */
  private function getFirstVariantId(object $variants) {
    foreach ($variants as $variant) {
      if ($variant && $variant->entity->isAvailable()) {
        return $variant->entity->id();
      }
    }

    if (isset($variants[0])) {
      return $variants[0]->entity->id();
    }

    return FALSE;
  }

}
