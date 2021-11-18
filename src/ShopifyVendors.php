<?php

namespace Drupal\neg_shopify;

use Drupal\neg_shopify\Entity\ShopifyVendor;
use Drupal\Core\Url;
use Drupal\neg_shopify\Utilities\Pager;

/**
 * ShopifyVendors Class.
 */
class ShopifyVendors {

  protected static $vendors = [];

  /**
   * Syncs shopify vendors with current products.
   */
  public static function syncVendors(array $specificVendors = []) {

    $vendors = self::fetchVendorsFromProducts($specificVendors);
    $vendorIds = [];

    foreach ($vendors as $vendor) {

      $vendor->tags = explode(',', $vendor->tags);
      $vendor->type = explode(',', $vendor->type);

      $modified = FALSE;

      // Attempt to load this variant.
      $entity = ShopifyVendor::loadBySlug($vendor->slug);
      if ($entity instanceof ShopifyVendor) {
        $oldTags = [];
        foreach ($entity->get('tags')->getValue() as $tag) {
          $oldTags[] = $tag['target_id'];
        }

        $oldType = $entity->get('type')->value;
        $vendor->status = $entity->get('status')->value;

        if ($oldTags != $vendor->tags || $oldType != $vendor->type[0]) {
          $entity->update((array) $vendor);
          $modified = TRUE;
        }
      }
      else {
        $vendor->status = TRUE;
        $entity = ShopifyVendor::create((array) $vendor);
        $modified = TRUE;
      }

      if ($entity) {
        if ($modified === TRUE) {
          $entity->save();
        }

        $vendorIds[] = $entity->id();
      }

    }

    if (count($specificVendors) === 0) {
      // Delete all vendors not in vendorIds.
      $toDelete = \Drupal::entityTypeManager()
        ->getStorage('shopify_vendor')
        ->getQuery()
        ->condition('id', $vendorIds, 'NOT IN')
        ->execute();

      $entities = (count($toDelete) > 0) ? ShopifyVendor::loadMultiple($toDelete) : [];
      foreach ($entities as $entity) {
        $entity->delete();
      }
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

    $total = ShopifyVendor::search(0, FALSE, $params)->countQuery()->execute()->fetchField();

    $vendors = [];
    $results = ShopifyVendor::search($page, $perPage, $params)->execute();
    $vids = [];
    foreach ($results as $result) {
      $vids[] = $result->id;
    }

    $availableVendors = (count($vids) > 0) ? ShopifyVendor::loadMultiple($vids) : [];

    $user = \Drupal::currentUser();
    foreach ($availableVendors as $vendor) {
      $count = 1;
      if (!$user->hasPermission('view shopify toolbar')) {
        $count = $vendor->getProductCount(TRUE);
      }

      if ($count > 0) {
        $vendors[] = $vendor->loadView('teaser', FALSE);
      }
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
    $page = \Drupal::request()->query->get('page') ?? 0;

    $variables['#attached']['library'][] = 'neg_shopify/collections';
    $variables['#attributes']['class'][] = 'shopify_collection';
    $variables['#attributes']['class'][] = 'pager';
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

    $count = ShopifyVendor::search(0, FALSE, $params)->countQuery()->execute()->fetchField();
    $variables['#attributes']['data-total'] = $count;

    $pager = new Pager([
      'page' => $page,
      'total' => $count,
      'perPage' => Settings::productsPerPage(),
    ]);

    $vendors = [];
    $results = ShopifyVendor::search($page, Settings::productsPerPage(), $params)->execute();
    $vids = [];
    foreach ($results as $result) {
      $vids[] = $result->id;
    }

    $availableVendors = (count($vids) > 0) ? ShopifyVendor::loadMultiple($vids) : [];

    foreach ($availableVendors as $vendor) {

      $count = 1;
      $user = \Drupal::currentUser();
      if (!$user->hasPermission('view shopify toolbar')) {
        $count = $vendor->getProductCount(TRUE);
      }

      if ($count > 0) {
        $vendors[] = $vendor->loadView();
      }
    }

    $variables['#vendors'] = [
      '#theme' => 'shopify_product_grid',
      '#products' => $vendors,
      '#products_label' => Settings::productsLabel(),
      '#count' => $count,
      '#controls' => FALSE,
      '#defaultSort' => Settings::defaultSortOrder(),
      '#pager' => $pager->render(),
      '#cache' => [
        'tags' => [
          'shopify_product_list',
          'shopify_vendors_list',
          'url.query_args',
          'config:neg_shopify.settings',
        ],
      ],
    ];

  }

  /**
   * Creates list of valid vendors from product data.
   */
  public static function fetchVendorsFromProducts(array $specificVendors = []) {
    $specificVendorsQuery = (count($specificVendors) > 0) ? 'AND product.id IN (' . implode(',', $specificVendors) . ')' : '';

    $query = <<<EOL
SELECT
  product.vendor as title, product.vendor_slug as slug, GROUP_CONCAT(distinct tags.tags_target_id ORDER BY tags_target_id DESC SEPARATOR ',') as tags, GROUP_CONCAT(distinct product.product_type ORDER BY product_type ASC SEPARATOR ',') as type
FROM
  shopify_product product
  left join shopify_product__tags tags on tags.entity_id = product.id
  left join taxonomy_term_field_data term on term.tid = tags.tags_target_id $specificVendorsQuery
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
