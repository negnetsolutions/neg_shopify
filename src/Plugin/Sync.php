<?php

namespace Drupal\neg_shopify\Plugin;

use Drupal\neg_shopify\Api\ShopifyService;
use Drupal\neg_shopify\ShopifyCollection;
use Drupal\taxonomy\Entity\Term;
use Drupal\neg_shopify\Settings;

/**
 * Shopify Product Sync Class.
 */
class Sync {

  /**
   * Deletes all vendors.
   */
  public static function deleteAllCustomers() {
    // Get the product queue.
    $queue = Settings::usersQueue();

    // Get all Products.
    $query = \Drupal::entityQuery('user')
      ->condition('roles', 'shopify_customer', 'CONTAINS');

    $ids = $query->execute();

    foreach ($ids as $id) {
      $queue->createItem([
        'op' => 'deleteUser',
        'id' => $id,
      ]);
    }

    \Drupal::messenger()->addStatus('Queue all products to be deleted!', TRUE);
  }

  /**
   * Deletes all vendors.
   */
  public static function deleteAllVendors() {
    // Get the product queue.
    $queue = Settings::queue();

    // Get all Products.
    $query = \Drupal::entityQuery('shopify_vendor');
    $ids = $query->execute();

    foreach ($ids as $id) {
      $queue->createItem([
        'op' => 'deleteVendor',
        'id' => $id,
      ]);
    }

    \Drupal::messenger()->addStatus('Queue all products to be deleted!', TRUE);
  }

  /**
   * Deletes all products.
   */
  public static function deleteAllProducts() {
    // Get the product queue.
    $queue = Settings::queue();

    // Empty the product queue.
    Settings::emptyQueue($queue);

    // Get all Products.
    $query = \Drupal::entityQuery('shopify_product');
    $ids = $query->execute();

    foreach ($ids as $id) {
      $queue->createItem([
        'op' => 'deleteProduct',
        'id' => $id,
      ]);
    }

    \Drupal::messenger()->addStatus('Queue all products to be deleted!', TRUE);
  }

  /**
   * Deletes all collections.
   */
  public static function deleteAllCollections() {

    // Get the collections queue.
    $queue = Settings::collectionsQueue();

    // Empty the product queue.
    Settings::emptyQueue($queue);

    // Get all collections.
    $ids = ShopifyCollection::loadAllIds();

    foreach ($ids as $id) {
      $queue->createItem([
        'op' => 'deleteCollection',
        'id' => $id,
      ]);
    }
    \Drupal::messenger()->addStatus('Queue all collections to be deleted!', TRUE);
  }

  /**
   * Deletes all tags.
   */
  public static function deleteAllTags() {

    $query = \Drupal::entityQuery('taxonomy_term');
    $query->condition('vid', ShopifyCollection::SHOPIFY_COLLECTION_TERM_VID);
    $ids = $query->execute();

    if (count($ids) > 0) {
      $terms = Term::loadMultiple($ids);
      foreach ($terms as $term) {
        try {
          $term->delete();
        }
        catch (\Exception $e) {
          \Drupal::messenger()->addError('Could not delete shopify tag id ' . $term->id(), TRUE);
        }
      }
    }

    \Drupal::messenger()->addStatus('Deleted all tags!', TRUE);
  }

  /**
   * Syncs all users.
   */
  public static function syncAllUsers() {

    // Only sync if allowing shopify logins.
    $allowShopifyLogins = (BOOL) Settings::config()->get('allow_shopify_users');
    if (!$allowShopifyLogins) {
      return;
    }

    $service = ShopifyService::instance();
    $queue = Settings::usersQueue();

    if ($queue->numberOfItems() > 0) {
      \Drupal::messenger()->addError('There are items in the user queue. Skipping sync until queue is cleared.', TRUE);
      return FALSE;
    }

    $users = ShopifyService::instance()->fetchAllUsers([
      'updated_at_min' => ShopifyService::getLastUsersUpdatedDate(),
      'limit' => '250',
      'fields' => 'id,email,first_name,last_name',
    ]);

    // Open the batch.
    $queue->createItem([
      'op' => 'openUsersBatch',
      'users_count' => count($users),
    ]);

    // Sync each user.
    foreach ($users as $user) {
      $queue->createItem([
        'op' => 'syncUser',
        'user' => $user,
      ]);
    }

    // Delete Orphaned Collections.
    $queue->createItem([
      'op' => 'deleteOrphanedUsers',
    ]);

    // Close batch.
    $queue->createItem([
      'op' => 'closeUsersBatch',
    ]);

    \Drupal::messenger()->addStatus('Queued Full Users Sync for next cron run!', TRUE);
  }

  /**
   * Full Collections Sync.
   */
  public static function syncAllCollections() {
    $service = ShopifyService::instance();
    $queue = Settings::collectionsQueue();
    $product_count = 0;

    if ($queue->numberOfItems() > 0) {
      \Drupal::messenger()->addError('There are items in the collection queue. Skipping sync until queue is cleared.', TRUE);
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

    \Drupal::messenger()->addStatus('Queued Full Collection Sync for next cron run!', TRUE);
  }

  /**
   * Full Sync.
   */
  public static function syncAllProducts() {
    $service = ShopifyService::instance();
    $queue = Settings::queue();
    $product_count = 0;

    if ($queue->numberOfItems() > 0) {
      \Drupal::messenger()->addError('There are items in the product queue. Skipping sync until queue is cleared.', TRUE);
      return FALSE;
    }

    $pages = $service->fetchAllPagedProducts([
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

    // Syncs vendors.
    $queue->createItem([
      'op' => 'syncVendors',
    ]);

    // Syncs listings.
    $visibleProducts = ShopifyService::instance()->fetchAllPagedListings([
      'limit' => 250,
    ]);

    $queue->createItem([
      'op' => 'syncListings',
      'visible_products' => $visibleProducts,
    ]);

    // Close batch.
    $queue->createItem([
      'op' => 'closeProductBatch',
    ]);

    \Drupal::messenger()->addStatus('Queued Full Product Sync for next cron run!', TRUE);
  }

}
