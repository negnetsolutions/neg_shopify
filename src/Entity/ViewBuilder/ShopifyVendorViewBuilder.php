<?php

namespace Drupal\neg_shopify\Entity\ViewBuilder;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\file\Entity\File;
use Drupal\neg_shopify\Entity\ShopifyProductVariant;
use Drupal\neg_shopify\Settings;
use Drupal\neg_shopify\Entity\ShopifyProduct;
use Drupal\Core\Url;

/**
 * Class ShopifyVendorViewBuilder.
 */
class ShopifyVendorViewBuilder extends EntityViewBuilder {

  /**
   * {@inheritdoc}
   */
  protected function alterBuild(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, $view_mode) {

    if ($settings = $display->getComponent('thumbnail')) {
      if ($entity->get('thumbnail')->isEmpty()) {
        $responsiveImageStyle = (isset($settings['settings']['responsive_image_style'])) ? $settings['settings']['responsive_image_style'] : 'rs_image';
        $build['thumbnail'] = $this->getDefaultThumbnail($entity, $responsiveImageStyle);
      }
    }

    if ($display->getComponent('products')) {
      // Get products.
      $build['products'] = $this->renderProducts($entity);
    }

  }

  /**
   * Get's a default thumbnail.
   */
  protected function getDefaultThumbnail(EntityInterface $entity, $rsImageStyle = 'rs_image') {
    $products = $entity->getProducts(1);

    $build = [];

    if (count($products) > 0) {
      $product = reset($products);
      if ($product->image->target_id) {
        $view = $product->image->view();
        if (count($view) > 0 && isset($view[0])) {
          $build = $view[0];
          $build['#theme'] = 'responsive_image_formatter';
          $build['#responsive_image_style_id'] = $rsImageStyle;
        }
      }
    }

    return $build;
  }

  /**
   * Renders products.
   */
  protected function renderProducts(EntityInterface $entity) {
    $products = $entity->getProducts(Settings::productsPerPage(), 0);
    $total = $entity->getProductCount();

    $productsBuild = [
      '#theme' => 'shopify_product_grid',
      '#products' => ShopifyProduct::loadView($products, 'store_listing'),
      '#count' => $total,
      '#defaultSort' => Settings::defaultSortOrder(),
      '#cache' => [
        'contexts' => ['user.roles'],
        'tags' => ['shopify_product_list', 'shopify_vendor:' . $entity->id()],
      ],
    ];

    $build = [
      '#theme' => 'shopify_vendor_product_grid',
      '#products' => $productsBuild,
    ];

    $build['#attached']['library'][] = 'neg_shopify/collections';
    $build['#attributes']['class'][] = 'shopify_collection';
    $build['#attributes']['class'][] = 'autopager';
    $build['#attributes']['data-perpage'] = Settings::productsPerPage();
    $build['#attributes']['data-endpoint'] = Url::fromRoute('neg_shopify.products.json')->toString();
    $build['#attributes']['data-sort'] = Settings::defaultSortOrder();
    $build['#attributes']['data-id'] = $entity->get('slug')->value;
    $build['#attributes']['data-type'] = 'vendor';

    return $build;
  }
}
