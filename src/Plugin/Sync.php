<?php

namespace Drupal\neg_shopify\Plugin;

use Drupal\neg_shopify\ShopifyService;
use Drupal\neg_shopify\Settings;

/**
 * Shopify Product Sync Class.
 */
class Sync {

  /**
   * Full Collections Sync.
   */
  public static function syncAllCollections() {
    $service = ShopifyService::instance();
    $queue = Settings::collectionsQueue();
    $product_count = 0;

    if ($queue->numberOfItems() > 0) {
      drupal_set_message('There are items in the queue to sync. Can not force sync until queue is clear!', 'error', TRUE);
      return FALSE;
    }

    $collections = ShopifyService::instance()->fetchCollections([
      'updated_at_min' => ShopifyService::getLastCollectionUpdatedDate(),
    ]);

    // Open the batch.
    $queue->createItem([
      'op' => 'openColectionBatch',
      'collection_count' => count($collections),
    ]);

    // Sync each collection.
    foreach ($collections as $collection) {
      $queue->createItem([
        'op' => 'syncCollection',
        'collection' => $collection,
      ]);
    }

    // Delete Orphaned Collections.
    $queue->createItem([
      'op' => 'deleteOrphanedCollections',
    ]);

    // Close batch.
    $queue->createItem([
      'op' => 'closeCollectionBatch',
    ]);

    drupal_set_message('Queued Full Collection Sync for next cron run!', 'status', TRUE);
  }

  /**
   * Full Sync.
   */
  public static function syncAllProducts() {
    $service = ShopifyService::instance();
    $queue = Settings::queue();
    $product_count = 0;

    if ($queue->numberOfItems() > 0) {
      drupal_set_message('There are items in the queue to sync. Can not force sync until queue is clear!', 'error', TRUE);
      return FALSE;
    }

    $pages = $service->fetchAllPagedProducts([
      'published_status' => 'published',
      'updated_at_min' => ShopifyService::getLastProductUpdatedDate(),
      'limit' => '250',
    ]);

    // Count all products.
    foreach ($pages as $products) {
      $product_count += count($products);
    }

    // Open the batch.
    $queue->createItem([
      'op' => 'openProductBatch',
      'product_count' => $product_count,
    ]);

    // Sync each product page.
    foreach ($pages as $products) {
      foreach ($products as $product) {
        $queue->createItem([
          'op' => 'syncProduct',
          'product' => $product,
        ]);
      }
    }

    // Delete orphaned products.
    $queue->createItem([
      'op' => 'deleteProducts',
    ]);

    // Close batch.
    $queue->createItem([
      'op' => 'closeProductBatch',
    ]);

    drupal_set_message('Queued Full Product Sync for next cron run!', 'status', TRUE);
  }

}
