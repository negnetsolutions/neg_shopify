<?php

namespace Drupal\neg_shopify\Entity\ViewBuilder;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\file\Entity\File;
use Drupal\neg_shopify\Entity\ShopifyProductVariant;
use Drupal\neg_shopify\Settings;
use Drupal\neg_shopify\Entity\ShopifyProduct;
use Drupal\neg_shopify\Entity\ShopifyProductSearch;
use Drupal\Core\Url;

/**
 * Class ShopifyVendorViewBuilder.
 */
class ShopifyVendorViewBuilder extends EntityViewBuilder {

  /**
   * {@inheritdoc}
   */
  protected function alterBuild(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, $view_mode) {

    $build['name'] = $entity->get('title')->value;
    $build['slug'] = $entity->get('slug')->value;
    $build['type'] = $entity->get('type')->value;
    $build['status'] = $entity->get('status')->value;

    if ($display->getComponent('products')) {
      // Get products.
      $build['products'] = $this->renderProducts($entity);
    }

  }

  /**
   * Renders products.
   */
  protected function renderProducts(EntityInterface $entity) {
    $params = [
      'sort' => Settings::defaultSortOrder(),
      'vendor_slug' => $entity->get('slug')->value,
    ];

    $search = new ShopifyProductSearch($params);
    $products = $search->search(0, Settings::productsPerPage());
    $total = $search->count();

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
