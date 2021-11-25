<?php

namespace Drupal\neg_shopify\Entity\ViewBuilder;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\neg_shopify\Settings;
use Drupal\neg_shopify\Entity\ShopifyProduct;
use Drupal\Core\Url;
use Drupal\neg_shopify\Utilities\Pager;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class ShopifyVendorViewBuilder.
 */
class ShopifyVendorViewBuilder extends EntityViewBuilder {

  /**
   * {@inheritdoc}
   */
  protected function alterBuild(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, $view_mode) {

    if ($display->getComponent('dynamic_thumbnail')) {
      $build['thumbnail'] = $build['dynamic_thumbnail'];
      if (isset($build['thumbnail'][0])) {
        if (!$entity->get('title')->isEmpty()) {
          $build['thumbnail'][0]['#item']->alt = $entity->get('title')->value;
        }
      }
    }

    if ($display->getComponent('products') && $view_mode === 'full') {
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
      $build = $product->renderThumbnail($rsImageStyle);
    }

    return $build;
  }

  /**
   * Renders products.
   */
  protected function renderProducts(EntityInterface $entity) {
    $page = \Drupal::request()->query->get('page') ?? 0;
    if (!is_numeric($page) || $page < 0) {
      throw new NotFoundHttpException();
    }

    $products = $entity->getProducts(Settings::productsPerPage(), $page);
    $total = $entity->getProductCount();

    $pager = new Pager([
      'page' => $page,
      'total' => $total,
      'perPage' => Settings::productsPerPage(),
    ]);

    $productsBuild = [
      '#theme' => 'shopify_product_grid',
      '#products' => ShopifyProduct::loadView($products, 'store_listing'),
      '#count' => $total,
      '#products_label' => Settings::productsLabel(),
      '#defaultSort' => Settings::defaultSortOrder(),
      '#pager' => $pager->render(),
    ];

    $build = [
      '#theme' => 'shopify_vendor_product_grid',
      '#products' => $productsBuild,
      '#cache' => [
        'contexts' => ['user.roles', 'url.query_args'],
        'tags' => [
          'shopify_vendor_products:' . $entity->id(),
        ],
      ],
    ];

    $build['#attributes']['data-total'] = $total;
    $build['#attached']['library'][] = 'neg_shopify/collections';
    $build['#attributes']['class'][] = 'shopify_collection';
    $build['#attributes']['class'][] = 'pager';
    $build['#attributes']['data-perpage'] = Settings::productsPerPage();
    $build['#attributes']['data-endpoint'] = Url::fromRoute('neg_shopify.products.json')->toString();
    $build['#attributes']['data-sort'] = Settings::defaultSortOrder();
    $build['#attributes']['data-id'] = $entity->get('slug')->value;
    $build['#attributes']['data-type'] = 'vendor';

    return $build;
  }

}
