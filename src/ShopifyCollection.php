<?php

namespace Drupal\neg_shopify;

use Drupal\neg_shopify\Entity\ShopifyProduct;
use Drupal\neg_shopify\Entity\ShopifyProductSearch;
use Drupal\neg_shopify\Api\ShopifyService;
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
      $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($collection_id);

      $fieldRules = json_decode($term->get('field_rules')->value, TRUE);
      $params['collection_sort'] = $fieldRules;

      if (!isset($params['sort']) && isset($params['collection_sort']['sort_order'])) {
        switch ($params['collection_sort']['sort_order']) {
          case 'manual':
            $params['sort'] = 'manual-ascending';
            break;

          case 'created-desc':
            $params['sort'] = 'date-descending';
            break;

          case 'created':
          case 'created-asc':
            $params['sort'] = 'date-ascending';
            break;

          case 'price-desc':
            $params['sort'] = 'price-descending';
            break;

          case 'price':
          case 'price-asc':
            $params['sort'] = 'price-ascending';
            break;

          case 'alpha-desc':
            $params['sort'] = 'title-descending';
            break;

          case 'alpha':
          case 'alpha-asc':
            $params['sort'] = 'title-ascending';
            break;
        }
      }

      switch ($term->get('field_type')->value) {
        case 'SmartCollection':
          $params['collection_disjunctive'] = (bool) $term->get('field_disjunctive')->value;
          $tags = self::cacheTags($term->id());
          break;

        default:
          // CustomCollection.
          $params['collection_id'] = $term->id();
          $tags = self::cacheTags($term->id(), FALSE);
          break;
      }

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
    $variables['#attributes']['data-id'] = 'all';
    $variables['#attributes']['data-type'] = 'collection';

    self::attachMetatag($variables, 'description', 'Shop all products.');

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
      '#products_label' => Settings::productsLabel(),
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

    $params = [];

    $fieldRules = json_decode($term->get('field_rules')->value, TRUE);
    $params['collection_sort'] = $fieldRules;

    if (isset($params['collection_sort']['sort_order'])) {
      switch ($params['collection_sort']['sort_order']) {
        case 'manual':
          $params['sort'] = 'manual-ascending';
          break;

        case 'created-desc':
          $params['sort'] = 'date-descending';
          break;

        case 'created':
        case 'created-asc':
          $params['sort'] = 'date-ascending';
          break;

        case 'price-desc':
          $params['sort'] = 'price-descending';
          break;

        case 'price':
        case 'price-asc':
          $params['sort'] = 'price-ascending';
          break;

        case 'alpha-desc':
          $params['sort'] = 'title-descending';
          break;

        case 'alpha':
        case 'alpha-asc':
          $params['sort'] = 'title-ascending';
          break;
      }
    }

    switch ($term->get('field_type')->value) {
      case 'SmartCollection':
        $params['collection_disjunctive'] = (bool) $term->get('field_disjunctive')->value;
        $tags = self::cacheTags($term->id());
        break;

      default:
        // CustomCollection.
        $params['collection_id'] = $term->id();
        $tags = self::cacheTags($term->id(), FALSE);
        break;
    }

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
    $variables['attributes']['data-type'] = 'collection';

    $allowManualSorting = FALSE;

    $params = [];

    $term = $variables['term'];
    self::attachMetatag($variables, 'description', 'Shop ' . $term->getName());

    $variables['attributes']['data-id'] = $term->id();

    $fieldRules = json_decode($term->get('field_rules')->value, TRUE);
    $params['collection_sort'] = $fieldRules;

    if (isset($params['collection_sort']['sort_order'])) {
      switch ($params['collection_sort']['sort_order']) {
        case 'manual':
          $params['sort'] = 'manual-ascending';
          break;

        case 'created-desc':
          $params['sort'] = 'date-descending';
          break;

        case 'created':
        case 'created-asc':
          $params['sort'] = 'date-ascending';
          break;

        case 'price-desc':
          $params['sort'] = 'price-descending';
          break;

        case 'price':
        case 'price-asc':
          $params['sort'] = 'price-ascending';
          break;

        case 'alpha-desc':
          $params['sort'] = 'title-descending';
          break;

        case 'alpha':
        case 'alpha-asc':
          $params['sort'] = 'title-ascending';
          break;
      }
    }

    if (isset($params['sort'])) {
      $variables['attributes']['data-sort'] = $params['sort'];
    }

    if (isset($params['collection_sort']['sort_order']) &&  $params['collection_sort']['sort_order'] === 'manual') {
      $allowManualSorting = TRUE;
    }

    switch ($term->get('field_type')->value) {
      case 'SmartCollection':
        $params['collection_disjunctive'] = (bool) $term->get('field_disjunctive')->value;
        $tags = self::cacheTags($term->id());
        break;

      default:
        // CustomCollection.
        $params['collection_id'] = $term->id();
        $tags = self::cacheTags($term->id(), FALSE);
        break;
    }

    $search = new ShopifyProductSearch($params);

    // Initial Page Render.
    $products = $search->search(0, Settings::productsPerPage());
    $total = $search->count();

    $variables['products'] = [
      '#theme' => 'shopify_product_grid',
      '#products' => ShopifyProduct::loadView($products, 'store_listing'),
      '#products_label' => Settings::productsLabel(),
      '#count' => $total,
      '#defaultSort' => $variables['attributes']['data-sort'],
      '#allowManualSort' => $allowManualSorting,
      '#cache' => [
        'contexts' => ['user.roles'],
        'tags' => $tags,
      ],
    ];
  }

  /**
   * Creates a metatag object.
   */
  protected static function attachMetatag(&$variables, $name, $content) {
    $tag = [
      '#type' => 'html_tag',
      '#tag' => 'meta',
      '#attributes' => [
        'name' => $name,
        'content' => $content,
      ],
    ];
    $variables['#attached']['html_head'][] = [$tag, $name];
  }

  /**
   * Get's cache tags.
   */
  public static function cacheTags($collection_id, $includeProducts = TRUE) {
    $tags = [
      'taxonomy_term:' . $collection_id,
    ];

    if ($includeProducts) {
      $tags[] = 'shopify_product_list';
    }

    return $tags;
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
    if (count($ids) > 0) {
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

    $saveTerms = TRUE;

    if ($term) {
      $term->name = $collection['title'];
      $term->description = [
        'value' => $collection['body_html'],
        'format' => filter_default_format(),
      ];
      $date = strtotime($collection['published_at']);
      $term->field_shopify_collection_pub = $date ? $date : 0;
      $term->field_handle = $collection['handle'];

      $fieldRules = [
        'sort_order' => $collection['sort_order'],
      ];

      // Check for type of collection.
      if (isset($collection['rules'])) {

        $saveTerms = FALSE;

        // Set no product sync unless it's a manual sort.
        if ($collection['sort_order'] !== 'manual') {
          $sync_products = FALSE;
        }

        // Set type.
        $term->field_type = [
          'value' => 'SmartCollection',
        ];

        // Set disjunctive.
        $term->field_disjunctive = [
          'value' => (boolean) $collection['disjunctive'],
        ];

        // Set rules.
        $fieldRules['rules'] = $collection['rules'];
      }
      else {
        // Set type.
        $term->field_type = [
          'value' => 'CustomCollection',
        ];

        // Set disjunctive false.
        $term->field_disjunctive = [
          'value' => FALSE,
        ];
      }

      // Set field_rules.
      $term->field_rules = [
        'value' => json_encode($fieldRules),
      ];
    }

    if ($term->save() && isset($collection['image']['src'])) {
      // Save the image for this term.
      self::saveImage($term, $collection['image']['src']);
    }

    if ($sync_products) {
      // Sync product information for this collection.
      $product_ids = self::syncProducts($collection, $saveTerms);

      $fieldRules['items'] = $product_ids;

      $term->set('field_rules', json_encode($fieldRules));
      $term->save();
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

    $saveTerms = TRUE;

    $fields = [
      'vid' => ShopifyProduct::SHOPIFY_COLLECTIONS_VID,
      'name' => $collection['title'],
      'description' => [
        'value' => $collection['body_html'],
        'format' => filter_default_format(),
      ],
      'field_handle' => $collection['handle'],
      'field_shopify_collection_id' => $collection['id'],
      'field_shopify_collection_pub' => $date ? $date : 0,
    ];

    $fieldRules = [
      'sort_order' => $collection['sort_order'],
    ];

    // Check for type of collection.
    if (isset($collection['rules'])) {

      $saveTerms = FALSE;

      // Set no product sync unless it's a manual sort.
      if ($collection['sort_order'] !== 'manual') {
        $sync_products = FALSE;
      }

      // Set type.
      $fields['field_type'] = [
        'value' => 'SmartCollection',
      ];

      // Set disjunctive.
      $fields['field_disjunctive'] = [
        'value' => (boolean) $collection['disjunctive'],
      ];

      // Set rules.
      $fieldRules['rules'] = $collection['rules'];
    }
    else {
      // Set type.
      $fields['field_type'] = [
        'value' => 'CustomCollection',
      ];

      // Set disjunctive false.
      $fields['field_disjunctive'] = [
        'value' => FALSE,
      ];
    }

    // Set field_rules.
    $fields['field_rules'] = [
      'value' => json_encode($fieldRules),
    ];

    $term = Term::create($fields);
    if ($term->save() && isset($collection['image']['src'])) {
      // Save the image for this term.
      self::saveImage($term, $collection['image']['src']);
    }

    if ($sync_products) {
      // Sync product information for this collection.
      $product_ids = self::syncProducts($collection, $saveTerms);

      $sortParams = [
        'sort_order' => $collection['sort_order'],
        'items' => $products_ids,
      ];

      $term->set('field_rules', json_encode($sortParams));
      $term->save();
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
    $file = system_retrieve_file($image_url, $directory, TRUE, FileSystemInterface::EXISTS_REPLACE);
    $term->field_shopify_collection_image = $file;
    $term->save();
  }

  /**
   * Sync product collect information for a given collection.
   *
   * @param object $collection
   *   Shopify collection.
   */
  protected static function syncProducts($collection, $saveTerms = TRUE) {
    $term = self::load($collection['id']);
    $collects = ShopifyService::instance()->fetchCollectionProducts($collection['id']);
    $product_ids = [];

    foreach ($collects as $c) {

      // Update this product information.
      $product = ShopifyProduct::loadByProductId($c['id']);
      if (!$product) {
        continue;
      }

      // Add item to product_ids.
      $product_ids[] = $c['id'];

      if ($saveTerms === TRUE) {
        // Only save product term connections if $saveTerms is TRUE.
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

    return $product_ids;
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
