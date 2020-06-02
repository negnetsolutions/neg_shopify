<?php

namespace Drupal\neg_shopify;

use Drupal\neg_shopify\Entity\ShopifyProduct;
use Drupal\neg_shopify\Entity\ShopifyProductSearch;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Url;

/**
 * ShopifyCollection Class.
 */
class ShopifyCollection {

  const SHOPIFY_COLLECTION_TERM_VID = 'shopify_tags';

  /**
   * Render's Json.
   */
  public static function renderJson($collection_id, $sortOrder = FALSE, $page = 0, $perPage = FALSE) {
    if ($sortOrder === FALSE) {
      $sortOrder = Settings::defaultSortOrder();
    }
    if ($perPage === FALSE) {
      $perPage = Settings::productsPerPage();
    }

    $params = [
      'sort' => $sortOrder,
    ];

    if ($collection_id != 'all') {
      $params['collection_id'] = $collection_id;
      $tags = self::cacheTags($collection_id);
    }
    else {
      $tags = self::cacheTags('all');
    }

    $search = new ShopifyProductSearch($params);

    $total = $search->count();
    $products = $search->search($page, $perPage);

    return [
      'count' => $total,
      'items' => ShopifyProduct::loadView($products, 'store_listing', FALSE),
    ];
  }

  /**
   * Renders Shopify All Collection.
   */
  public static function renderAll(&$variables) {

    $variables['#attached']['library'][] = 'neg_shopify/collections';
    $variables['#attributes']['class'][] = 'shopify_collection';
    $variables['#attributes']['class'][] = 'autopager';
    $variables['#attributes']['data-perpage'] = Settings::productsPerPage();
    $variables['#attributes']['data-endpoint'] = Url::fromRoute('neg_shopify.products.json')->toString();
    $variables['#attributes']['data-sort'] = Settings::defaultSortOrder();
    $variables['#attributes']['data-collection'] = 'all';

    $params = [
      'sort' => Settings::defaultSortOrder(),
    ];

    $tags = self::cacheTags('all');
    $search = new ShopifyProductSearch($params);

    // Initial Page Render.
    $products = $search->search(0, Settings::productsPerPage());
    $total = $search->count();

    $variables['#products'] = [
      '#theme' => 'shopify_product_grid',
      '#products' => ShopifyProduct::loadView($products, 'store_listing'),
      '#count' => $total,
      '#defaultSort' => Settings::defaultSortOrder(),
      '#cache' => [
        'contexts' => ['user.roles'],
        'tags' => $tags,
      ],
    ];
  }

  /**
   * Renders the collection paragraph.
   */
  public static function renderParagraph(&$variables) {
    $variables['attributes']['class'][] = 'shopify_paragraph_collection';

    $limit = (isset($variables['products_to_display'])) ? (int) $variables['products_to_display'] : 5;
    $term = $variables['term'];

    $params = [
      'sort' => Settings::defaultSortOrder(),
      'collection_id' => $term->id(),
    ];

    $tags = self::cacheTags($term->id());

    $search = new ShopifyProductSearch($params);
    $products = $search->search(0, $limit);
    $totalProducts = $search->count();
    $showMore = (count($products) < $totalProducts) ? TRUE : FALSE;

    $variables['products'] = [
      '#theme' => 'shopify_paragraph_product_grid',
      '#products' => ShopifyProduct::loadView($products, 'store_listing'),
      '#totalProducts' => $totalProducts,
      '#showMore' => $showMore,
      '#more_url' => Url::fromRoute('entity.taxonomy_term.canonical', [
        'taxonomy_term' => $term->id(),
      ])->toString(),
      '#cache' => [
        'contexts' => ['url', 'user.roles'],
        'tags' => $tags,
      ],
    ];
  }

  /**
   * Renders a Shopify Collection.
   */
  public static function render(&$variables) {

    $variables['#attached']['library'][] = 'neg_shopify/collections';
    $variables['attributes']['class'][] = 'shopify_collection';
    $variables['attributes']['class'][] = 'autopager';
    $variables['attributes']['data-perpage'] = Settings::productsPerPage();
    $variables['attributes']['data-endpoint'] = Url::fromRoute('neg_shopify.products.json')->toString();
    $variables['attributes']['data-sort'] = Settings::defaultSortOrder();

    $params = [
      'sort' => Settings::defaultSortOrder(),
    ];

    $term = $variables['term'];
    $variables['attributes']['data-collection'] = $term->id();
    $params['collection_id'] = $term->id();
    $tags = self::cacheTags($term->id());

    $search = new ShopifyProductSearch($params);

    // Initial Page Render.
    $products = $search->search(0, Settings::productsPerPage());
    $total = $search->count();

    $variables['products'] = [
      '#theme' => 'shopify_product_grid',
      '#products' => ShopifyProduct::loadView($products, 'store_listing'),
      '#count' => $total,
      '#defaultSort' => Settings::defaultSortOrder(),
      '#cache' => [
        'contexts' => ['user.roles'],
        'tags' => $tags,
      ],
    ];
  }

  /**
   * Get's cache tags.
   */
  public static function cacheTags($collection_id) {
    return [
      'shopify_product',
      'taxonomy_term:' . $collection_id,
    ];
  }

  /**
   * Loads a collection term based on the collection ID.
   *
   * @param int $collection_id
   *   Shopify collection ID.
   *
   * @return \Term
   *   Shopify collection.
   */
  public static function load($collection_id) {
    $query = \Drupal::entityQuery('taxonomy_term');
    $query->condition('field_shopify_collection_id', $collection_id);
    $ids = $query->execute();
    if ($ids) {
      $terms = Term::loadMultiple($ids);
      return reset($terms);
    }
    return FALSE;
  }

  /**
   * Loads all Shopify collection IDs.
   *
   * @return array
   *   Shopify collections IDs.
   */
  public static function loadAllIds() {
    $query = \Drupal::entityQuery('taxonomy_term');
    $query->condition('vid', ShopifyProduct::SHOPIFY_COLLECTIONS_VID);
    $ids = $query->execute();
    if ($ids) {
      return $ids;
    }
    return [];
  }

  /**
   * Update a Shopify collection with new information.
   *
   * @param object $collection
   *   Shopify collection.
   * @param bool $sync_products
   *   Whether or not to sync product information during update.
   *
   * @return \Term
   *   Shopify collection.
   */
  public static function update($collection, $sync_products = FALSE) {
    $term = self::load($collection['id']);
    if ($term) {
      $term->name = $collection['title'];
      $term->description = [
        'value' => $collection['body_html'],
        'format' => filter_default_format(),
      ];
      $date = strtotime($collection['published_at']);
      $term->field_shopify_collection_pub = $date ? $date : 0;
      $term->field_handle = $collection['handle'];
    }
    if ($term->save() && isset($collection['image']['src'])) {
      // Save the image for this term.
      self::saveImage($term, $collection['image']['src']);
    }
    if ($sync_products) {
      // Sync product information for this collection.
      self::syncProducts($collection);
    }
    return $term;
  }

  /**
   * Create a new collection in the system and sync products.
   *
   * @param object $collection
   *   Shopify collection.
   * @param bool $sync_products
   *   Whether or not to sync product information during creation.
   *
   * @return \Term
   *   Shopify collection.
   */
  public static function create($collection, $sync_products = FALSE) {
    $date = strtotime($collection['published_at']);
    $term = Term::create([
      'vid' => ShopifyProduct::SHOPIFY_COLLECTIONS_VID,
      'name' => $collection['title'],
      'description' => [
        'value' => $collection['body_html'],
        'format' => filter_default_format(),
      ],
      'field_handle' => $collection['handle'],
      'field_shopify_collection_id' => $collection['id'],
      'field_shopify_collection_pub' => $date ? $date : 0,
    ]);
    if ($term->save() && isset($collection['image']['src'])) {
      // Save the image for this term.
      self::saveImage($term, $collection['image']['src']);
    }
    if ($sync_products) {
      // Sync product information for this collection.
      self::syncProducts($collection);
    }
    return $term;
  }

  /**
   * Saves an image for a Shopify collection.
   *
   * @param \Term $term
   *   Taxonomy term entity.
   * @param string $image_url
   *   Remote image URL for the collection image.
   */
  protected static function saveImage(Term $term, $image_url) {
    $directory = file_build_uri('shopify_images');
    if (!\Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY)) {
      // If our directory doesn't exist and can't be created, use the default.
      $directory = NULL;
    }
    $file = system_retrieve_file($image_url, $directory, TRUE, FILE_EXISTS_REPLACE);
    $term->field_shopify_collection_image = $file;
    $term->save();
  }

  /**
   * Sync product collect information for a given collection.
   *
   * @param object $collection
   *   Shopify collection.
   */
  protected static function syncProducts($collection) {
    $term = self::load($collection['id']);
    $collects = ShopifyService::instance()->fetchCollectionProducts($collection['id']);
    foreach ($collects as $c) {

      // Update this product information.
      $product = ShopifyProduct::loadByProductId($c['id']);
      if (!$product) {
        continue;
      }
      foreach ($product->collections as $key => $item) {
        if ($item->target_id && ($item->target_id == $term->id())) {
          // Product already in collection.
          // Check if this collection is active.
          if ($term->field_shopify_collection_pub->value == 0) {
            // Remove this collection from the product.
            $product->collections->removeItem($key);
            $product->save();
          }
          continue 2;
        }
      }
      if ($term->field_shopify_collection_pub->value != 0) {
        $product->collections[] = $term;
        $product->save();
      }
    }
  }

  /**
   * Delete all Shopify collections.
   */
  public static function deleteAll() {
    $ids = self::loadAllIds();
    foreach ($ids as $id) {
      $term = Term::load($id);
      $term->delete();
    }
  }

  /**
   * Deletes orphaned collections.
   */
  public static function deleteOrphaned() {
    $shopifyCollections = ShopifyService::instance()->fetchCollections();
    $shopifyCollectionIds = [];
    $deletedCollections = [];

    foreach ($shopifyCollections as $collection) {
      $shopifyCollectionIds[] = $collection['id'];
    }

    $localCollections = Term::loadMultiple(self::loadAllIds());
    foreach ($localCollections as $collection) {
      $id = $collection->field_shopify_collection_id->first()->getValue()['value'];

      // Test to see if collection is still in shopify.
      if (!in_array($id, $shopifyCollectionIds)) {
        // If not; delete it.
        $collection->delete();
        $deletedCollections[] = $collection;
      }
    }

    return $deletedCollections;
  }

}
