<?php

namespace Drupal\neg_shopify\Entity\ViewBuilder;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\file\Entity\File;
use Drupal\neg_shopify\Entity\ShopifyProductVariant;

/**
 * Class ShopifyProductVariantViewBuilder.
 */
class ShopifyProductVariantViewBuilder extends EntityViewBuilder {

  /**
   * {@inheritdoc}
   */
  protected function alterBuild(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, $view_mode) {

    $build['#available'] = $entity->isAvailable();

    if ($display->getComponent('dynamic_product_image') || $display->getComponent('product_vendor') || $display->getComponent('product_title')) {
      $product = $entity->getProduct();
    }

    if ($display->getComponent('dynamic_product_image')) {
      if ($entity->image->isEmpty()) {
        $view = $product->get('image')->get(0)->view();
      }
      else {
        $view = $entity->get('image')->get(0)->view();
      }

      $build['dynamic_product_image'] = $view;
      $build['dynamic_product_image']['#theme'] = 'responsive_image_formatter';
      $build['dynamic_product_image']['#responsive_image_style_id'] = 'rs_5x4';
    }

    if ($display->getComponent('product_title')) {
      $build['product_title'] = [
        '#markup' => '<div class="title">' . $product->get('title')->value . '</div>',
      ];
    }

    if ($display->getComponent('product_vendor')) {
      $build['product_vendor'] = [
        '#markup' => '<div class="vendor">' . $product->get('vendor')->value . '</div>',
      ];
    }
  }

}
