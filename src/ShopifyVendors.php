<?php

namespace Drupal\neg_shopify;

use Drupal\neg_shopify\Entity\ShopifyProduct;
use Drupal\neg_shopify\Entity\ShopifyProductSearch;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\Core\Url;

/**
 * ShopifyVendors Class.
 */
class ShopifyVendors {

  protected static $vendors = FALSE;

  /**
   * Fetches products by vendor slug.
   */
  public static function renderProductsByVendorSlug(string $slug, &$variables) {

    $variables['#attached']['library'][] = 'neg_shopify/collections';
    $variables['#attributes']['class'][] = 'shopify_collection';
    $variables['#attributes']['class'][] = 'autopager';
    $variables['#attributes']['data-perpage'] = Settings::productsPerPage();
    $variables['#attributes']['data-endpoint'] = Url::fromRoute('neg_shopify.products.json')->toString();
    $variables['#attributes']['data-sort'] = Settings::defaultSortOrder();
    $variables['#attributes']['data-id'] = $slug;
    $variables['#attributes']['data-type'] = 'vendor';

    $params = [
      'sort' => Settings::defaultSortOrder(),
      'vendor_slug' => $slug,
    ];

    $search = new ShopifyProductSearch($params);
    $products = $search->search(0, Settings::productsPerPage());
    $total = $search->count();

    $variables['#products'] = [
      '#theme' => 'shopify_product_grid',
      '#products' => ShopifyProduct::loadView($products, 'store_listing'),
      '#count' => $total,
      '#defaultSort' => Settings::defaultSortOrder(),
      '#cache' => [
        'contexts' => ['user.roles'],
        'tags' => ['shopify_product_list'],
      ],
    ];
  }

  /**
   * Render's Json.
   */
  public static function renderJson($slug, $sortOrder = FALSE, $page = 0, $perPage = FALSE) {
    if ($sortOrder === FALSE) {
      $sortOrder = Settings::defaultSortOrder();
    }
    if ($perPage === FALSE) {
      $perPage = Settings::productsPerPage();
    }

    $params = [
      'sort' => $sortOrder,
      'vendor_slug' => $slug,
    ];

    $tags = ['shopify_product_list'];

    $search = new ShopifyProductSearch($params);

    $total = $search->count();
    $products = $search->search($page, $perPage);

    return [
      'count' => $total,
      'items' => ShopifyProduct::loadView($products, 'store_listing', FALSE),
    ];
  }

  /**
   * Fetches Vendor name by vendor slug.
   */
  public static function fetchVendorNameBySlug(string $slug) {
    $query = <<<EOL
SELECT
  product.vendor
FROM
  shopify_product product
WHERE
  product.vendor_slug = :handle
EOL;

    $result = \Drupal::database()
      ->queryRange($query, 0, 1, [
        ':handle' => $slug,
      ]);

    foreach ($result as $record) {
      return $record->vendor;
    }

    throw new NotFoundHttpException();
  }

  /**
   * Fetches available vendors.
   */
  public static function fetchAvailableVendors() {
    if (self::$vendors === FALSE) {
      $query = <<<EOL
SELECT
  product.vendor, product.vendor_slug
FROM
  shopify_product product
WHERE
  product.is_available = 1
GROUP BY
  product.vendor_slug, product.vendor
ORDER BY
  product.vendor ASC
EOL;

      $result = \Drupal::database()
        ->query($query);

      self::$vendors = [];
      foreach ($result as $record) {
        self::$vendors[] = [
          'vendor' => $record->vendor,
          'slug' => $record->vendor_slug,
        ];
      }
    }

    return self::$vendors;
  }

}
