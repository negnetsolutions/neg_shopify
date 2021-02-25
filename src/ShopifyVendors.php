<?php

namespace Drupal\neg_shopify;

use Drupal\neg_shopify\Entity\ShopifyProduct;
use Drupal\neg_shopify\Entity\ShopifyProductSearch;
use Drupal\neg_shopify\Entity\ShopifyVendor;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\Core\Url;
use Drupal\Core\Render\RenderContext;

/**
 * ShopifyVendors Class.
 */
class ShopifyVendors {

  protected static $vendors = [];

  /**
   * Syncs shopify vendors with current products.
   */
  public static function syncVendors() {

    $vendors = self::fetchVendorsFromProducts();
    $vendorIds = [];

    foreach ($vendors as $vendor) {

      $vendor->tags = explode(',', $vendor->tags);
      $vendor->type = explode(',', $vendor->type);
      $vendor->status = TRUE;

      // Attempt to load this variant.
      $entity = ShopifyVendor::loadBySlug($vendor->slug);
      if ($entity instanceof ShopifyVendor) {
        $entity->update((array) $vendor);
      }
      else {
        $entity = ShopifyVendor::create((array) $vendor);
      }

      if ($entity) {
        $entity->save();
        $vendorIds[] = $entity->id();
      }

    }

    // Delete all vendors not in vendorIds.
    $toDelete = \Drupal::entityTypeManager()
      ->getStorage('shopify_vendor')
      ->getQuery()
      ->condition('id', $vendorIds, 'NOT IN')
      ->execute();

    foreach ($toDelete as $entity) {

      $entity->delete();
    }

  }

  /**
   * Render's vendors page json.
   */
  public static function renderVendorsPageJson($params, $sortOrder = FALSE, $page = 0, $perPage = FALSE) {
    if ($sortOrder === FALSE) {
      $sortOrder = Settings::defaultSortOrder();
    }
    if ($perPage === FALSE) {
      $perPage = Settings::productsPerPage();
    }

    $defaults = [
      'tags' => [],
      'types' => [],
    ];
    $params = array_merge($defaults, $params);

    $total = ShopifyVendor::search(0, FALSE, $params)->count()->execute();

    $vendors = [];
    $vids = ShopifyVendor::search($page, $perPage, $params)->execute();
    $availableVendors = ShopifyVendor::loadMultiple($vids);

    foreach ($availableVendors as $vendor) {
      $vendors[] = $vendor->loadView('teaser', FALSE);
    }

    return [
      'count' => $total,
      'items' => $vendors,
    ];
  }

  /**
   * Renders vendors page.
   */
  public static function renderVendorsPage(&$variables, $params = []) {
    $variables['#attached']['library'][] = 'neg_shopify/collections';
    $variables['#attributes']['class'][] = 'shopify_collection';
    $variables['#attributes']['class'][] = 'autopager';
    $variables['#attributes']['data-perpage'] = Settings::productsPerPage();
    $variables['#attributes']['data-endpoint'] = Url::fromRoute('neg_shopify.products.json')->toString();
    $variables['#attributes']['data-sort'] = Settings::defaultSortOrder();

    $defaults = [
      'tags' => [],
      'types' => [],
    ];
    $params = array_merge($defaults, $params);

    $variables['#attributes']['data-id'] = 'tags_' . implode('_', $params['tags']) . '[]types_' . implode('_', $params['types']);
    $variables['#attributes']['data-type'] = 'vendors';

    $count = ShopifyVendor::search(0, FALSE, $params)->count()->execute();

    $vendors = [];
    $vids = ShopifyVendor::search(0, Settings::productsPerPage(), $params)->execute();
    $availableVendors = ShopifyVendor::loadMultiple($vids);

    foreach ($availableVendors as $vendor) {
      $vendors[] = $vendor->loadView();
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
   * Creates list of valid vendors from product data.
   */
  public static function fetchVendorsFromProducts() {
    $query = <<<EOL
SELECT
  product.vendor as title, product.vendor_slug as slug, GROUP_CONCAT(distinct tags.tags_target_id ORDER BY tags_target_id DESC SEPARATOR ',') as tags, GROUP_CONCAT(distinct product.product_type ORDER BY product_type ASC SEPARATOR ',') as type
FROM
  shopify_product product
  left join shopify_product__tags tags on tags.entity_id = product.id
  left join taxonomy_term_field_data term on term.tid = tags.tags_target_id
WHERE
  (product.is_available = 1 or product.is_preorder = 1)
GROUP BY
  product.vendor_slug, product.vendor
ORDER BY
  product.vendor ASC
EOL;
    $result = \Drupal::database()
      ->query($query);

    return $result;
  }

}
