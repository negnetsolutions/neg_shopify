<?php

namespace Drupal\neg_shopify\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\neg_shopify\Entity\ShopifyProduct;
use Drupal\neg_shopify\Settings;
use Drupal\taxonomy\Entity\Term;
use Drupal\neg_shopify\ShopifyCollection;

/**
 * Class SyncShopify.
 */
class SyncShopify extends QueueWorkerBase {

  /**
   * Processes a queue item.
   */
  public function processItem($data) {

    switch ($data['op']) {

      case 'openProductBatch':
        Settings::log('Starting Product Sync Batch. (Syncing %count Products)', ['%count' => $data['product_count']], 'info');
        break;

      case 'syncProduct':
        $product = $data['product'];
        $p = ShopifyProduct::updateProduct($product);
        if ($p !== FALSE) {
          Settings::log('Synced Product id: %id', ['%id' => $product['id'], 'debug']);
        }

        break;

      case 'deleteProduct':
        try {
          $product = ShopifyProduct::load($data['id']);
          $product->delete();
        }
        catch (\Exception $e) {
          Settings::log('Could not delete shopify product id %id', ['%id' => $data['id']], 'error');
        }
        break;

      case 'deleteProducts':
        $products = ShopifyProduct::deleteOrphanedProducts([
          'published_status' => 'published',
        ]);

        $deleted = count($products);

        if ($deleted > 0) {
          Settings::log('Deleted %count orphaned products', ['%count' => $deleted], 'debug');
        }

        break;

      case 'closeProductBatch':
        $last_updated = time();
        $datetime = new \DateTime();
        $last_updated_human = $datetime->format('Y-m-d H:i');
        \Drupal::state()->set('neg_shopify.last_product_sync', $last_updated);
        Settings::log('Closing Product Sync Batch. Last Sync: %last', ['%last' => $last_updated_human], 'info');
        break;

      case 'deleteCollection':
        $term = Term::load($data['id']);
        try {
          $term->delete();
        }
        catch (\Exception $e) {
          Settings::log('Could not delete shopify collection id %id', ['%id' => $data['id']], 'error');
        }
        break;

      case 'openColectionBatch':
        Settings::log('Starting Collection Sync Batch. (Syncing %count Collections)', ['%count' => $data['collection_count']], 'info');
        break;

      case 'syncCollection':
        $collection = $data['collection'];
        $term = ShopifyCollection::load($collection['id']);
        if (!$term) {
          // Need to create a new collection.
          ShopifyCollection::create($collection, TRUE);
        }
        else {
          ShopifyCollection::update($collection, TRUE);
        }
        Settings::log('Synced %id Collection', ['%id' => $collection['title'], 'debug']);
        break;

      case 'deleteOrphanedCollections':

        $collections = ShopifyCollection::deleteOrphaned();

        $deleted = count($collections);

        if ($deleted > 0) {
          Settings::log('Deleted %count orphaned collections', ['%count' => $deleted], 'debug');
        }
        break;

      case 'closeCollectionBatch':
        $config = Settings::editableConfig();
        $last_updated = time();
        $datetime = new \DateTime();
        $last_updated_human = $datetime->format('Y-m-d H:i');
        \Drupal::state()->set('neg_shopify.last_collection_sync', $last_updated);
        Settings::log('Closing Collection Sync Batch. Last Sync: %last', ['%last' => $last_updated_human], 'info');
        break;

        case 'openUsersBatch':
          Settings::log('Starting Users Sync Batch. (Syncing %count Users)', ['%count' => $data['users_count']], 'info');
          break;

        case 'syncUser':
          $user = $data['user'];
          break;

        case 'deleteOrphanedUsers':

          break;

        case 'closeUsersBatch':
          $config = Settings::editableConfig();
          $last_updated = time();
          $datetime = new \DateTime();
          $last_updated_human = $datetime->format('Y-m-d H:i');
          \Drupal::state()->set('neg_shopify.last_users_sync', $last_updated);
          Settings::log('Closing Users Sync Batch. Last Sync: %last', ['%last' => $last_updated_human], 'info');
          break;
    }
  }

}
