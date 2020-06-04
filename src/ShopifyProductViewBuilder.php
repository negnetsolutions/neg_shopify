<?php

namespace Drupal\neg_shopify;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\file\Entity\File;
use Drupal\neg_shopify\Entity\ShopifyProductVariant;

/**
 * Class ShopifyProductViewBuilder.
 */
class ShopifyProductViewBuilder extends EntityViewBuilder {

  /**
   * {@inheritdoc}
   */
  protected function alterBuild(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, $view_mode) {
    if (isset($build['body_html'])) {
      $build['body_html'][0]['#format'] = 'html';
    }

    if ($variant_id = \Drupal::request()->get('variant_id')) {
      $active_variant = ShopifyProductVariant::loadByVariantId($variant_id);
    }
    else {
      $active_variant = ShopifyProductVariant::load($entity->variants->get(0)->target_id);
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

    if ($display->getComponent('dynamic_product_image')) {
      $view = [];
      // Setup the image from the active variant.
      if ($active_variant instanceof ShopifyProductVariant) {
        if ($active_variant->image->target_id) {
          $view = $active_variant->image->view();
        }
        elseif ($entity->image->target_id) {
          $view = $entity->image->view();
        }
        if (count($view) > 0 && isset($view[0])) {
          $build['dynamic_product_image'] = $view[0];
          $build['dynamic_product_image']['#theme'] = 'responsive_image_formatter';
          $build['dynamic_product_image']['#responsive_image_style_id'] = 'rs_image';
        }
      }
    }

    if ($display->getComponent('active_variant')) {

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

      if ($build['#view_mode'] === 'full') {
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

}
