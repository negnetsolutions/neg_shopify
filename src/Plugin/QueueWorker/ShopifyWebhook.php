<?php

namespace Drupal\neg_shopify\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\neg_shopify\Entity\ShopifyProduct;
use Drupal\neg_shopify\ShopifyCollection;
use Drupal\neg_shopify\Settings;

/**
 * Class ShopifyWebhook.
 */
class ShopifyWebhook extends QueueWorkerBase {

  /**
   * Processes a queue item.
   */
  public function processItem($data) {

    switch ($data['hook']) {
      case 'products/create':
      case 'products/update':
        $product = ShopifyProduct::updateProduct($data['payload']);
        if ($product !== FALSE) {
          Settings::log('Synced Product id: %id', ['%id' => $data['payload']['id'], 'debug']);
        }
        else {
          throw new \Exception('Could not update product id: ' . $data['payload']['id']);
        }
        break;

      case 'products/delete':
        $product = ShopifyProduct::loadByProductId($data['payload']['id']);
        if ($product !== FALSE) {
          $product->delete();
          Settings::log('Deleted Product id: %id', ['%id' => $data['payload']['id'], 'debug']);
        }
        break;

      case 'collections/create':
      case 'collections/update':
        $collection = $data['payload'];
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

      case 'collections/delete':
        $collection = $data['payload'];
        $term = ShopifyCollection::load($collection['id']);
        if ($term !== FALSE) {
          $term->delete();
          Settings::log('Deleted %id Collection', ['%id' => $collection['id'], 'debug']);
        }
        break;
    }
  }

}
