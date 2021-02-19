<?php

namespace Drupal\neg_shopify;

use Drupal\neg_shopify\Entity\ShopifyProduct;
use Drupal\neg_shopify\Entity\ShopifyProductSearch;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\image\Entity\ImageStyle;
use Drupal\Core\Url;
use Drupal\Core\Render\RenderContext;

/**
 * ShopifyVendors Class.
 */
class ShopifyVendors {

  protected static $vendors = [];

  /**
   * Render's vendors page json.
   */
  public static function renderVendorsPageJson($tags, $sortOrder = FALSE, $page = 0, $perPage = FALSE) {
    if ($sortOrder === FALSE) {
      $sortOrder = Settings::defaultSortOrder();
    }
    if ($perPage === FALSE) {
      $perPage = Settings::productsPerPage();
    }

    $vendors = [];
    foreach (self::fetchAvailableVendors($page, $perPage, $tags) as $vendor) {
      $build = self::buildVendorRenderArray($vendor);
      $rendered_view = NULL;
      \Drupal::service('renderer')->executeInRenderContext(new RenderContext(), function () use (&$build, &$rendered_view) {
        $rendered_view = render($build);
      });

      $vendors[] = $rendered_view;
    }

    $total = count(self::fetchAvailableVendors(0, FALSE, $tags));

    return [
      'count' => $total,
      'items' => $vendors,
    ];
  }

  /**
   * Renders vendors page.
   */
  public static function renderVendorsPage(&$variables, $tags = []) {
    $variables['#attached']['library'][] = 'neg_shopify/collections';
    $variables['#attributes']['class'][] = 'shopify_collection';
    $variables['#attributes']['class'][] = 'autopager';
    $variables['#attributes']['data-perpage'] = Settings::productsPerPage();
    $variables['#attributes']['data-endpoint'] = Url::fromRoute('neg_shopify.products.json')->toString();
    $variables['#attributes']['data-sort'] = Settings::defaultSortOrder();
    $variables['#attributes']['data-id'] = 'tags_' . implode('_', $tags);
    $variables['#attributes']['data-type'] = 'vendors';

    $count = count(self::fetchAvailableVendors(0, FALSE, $tags));

    $vendors = [];
    foreach (self::fetchAvailableVendors(0, Settings::productsPerPage(), $tags) as $vendor) {
      $vendors[] = self::buildVendorRenderArray($vendor);
    }

    $variables['#vendors'] = [
      '#theme' => 'shopify_product_grid',
      '#products' => $vendors,
      '#count' => $count,
      '#controls' => FALSE,
      '#defaultSort' => Settings::defaultSortOrder(),
      '#cache' => [
        'tags' => ['shopify_product_list'],
      ],
    ];

  }

  /**
   * Builds render array for vendor.
   */
  protected static function buildVendorRenderArray($vendor) {
    $build = [
      '#theme' => 'shopify-vendor',
      '#name' => $vendor['vendor'],
      '#slug' => $vendor['slug'],
      '#attributes' => [
        'class' => ['shopify-vendor'],
      ],
    ];

    $params = [
      'sort' => Settings::defaultSortOrder(),
      'vendor_slug' => $vendor['slug'],
    ];

    $search = new ShopifyProductSearch($params);
    $products = $search->search(0, 1);

    if (count($products) > 0) {
      $product = reset($products);
      if ($product->image->target_id) {

        $file = $product->image->entity;

        $imageVars = array(
          'responsive_image_style_id' => 'rs_image',
          'uri' => $file->getFileUri(),
        );

        // The image.factory service will check if our image is valid.
        $image = \Drupal::service('image.factory')->get($file->getFileUri());
        if ($image->isValid()) {
          $imageVars['width'] = $image->getWidth();
          $imageVars['height'] = $image->getHeight();
        }
        else {
          $imageVars['width'] = $imageVars['height'] = NULL;
        }

        $build['#image'] = [
          '#theme' => 'responsive_image',
          '#width' => $imageVars['width'],
          '#height' => $imageVars['height'],
          '#responsive_image_style_id' => $imageVars['responsive_image_style_id'],
          '#uri' => $imageVars['uri'],
        ];

        // Add the file entity to the cache dependencies.
        // This will clear our cache when this entity updates.
        $renderer = \Drupal::service('renderer');
        $renderer->addCacheableDependency($build['#image'], $file);
      }
    }

    return $build;
  }

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
        'tags' => ['shopify_vendor:' . $slug],
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

    $tags = ['shopify_vendor:' . $slug];

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
  public static function fetchAvailableVendors($page = 0, $perPage = FALSE, $tags = []) {
    $tagCondition = NULL;

    if (count($tags) > 0) {
      // TODO. product.product_type == 'object|artwork';
      $tags = implode(',', $tags);
      $tagCondition = " AND tags.tags_target_id IN ($tags)";
    }
    if (!isset(self::$vendors[$page . '_' . (int) $perPage])) {
      $query = <<<EOL
SELECT
  product.vendor, product.vendor_slug, GROUP_CONCAT(distinct tags.tags_target_id ORDER BY tags_target_id DESC SEPARATOR ',') as t
FROM
  shopify_product product
  left join shopify_product__tags tags on tags.entity_id = product.id
  left join taxonomy_term_field_data term on term.tid = tags.tags_target_id
WHERE
  (product.is_available = 1 or product.is_preorder = 1)
  {$tagCondition}
GROUP BY
  product.vendor_slug, product.vendor
ORDER BY
  product.vendor ASC
EOL;

      if ($perPage !== FALSE) {
        $start = $page * $perPage;
        $query .= " LIMIT $start, $perPage";
      }

      $result = \Drupal::database()
        ->query($query);

      self::$vendors[$page . '_' . (int) $perPage] = [];
      foreach ($result as $record) {
        self::$vendors[$page . '_' . (int) $perPage][] = [
          'vendor' => $record->vendor,
          'slug' => $record->vendor_slug,
        ];
      }
    }

    return self::$vendors[$page . '_' . (int) $perPage];
  }

}
