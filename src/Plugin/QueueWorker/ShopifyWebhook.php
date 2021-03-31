<?php

namespace Drupal\neg_shopify\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\neg_shopify\Entity\ShopifyProduct;
use Drupal\neg_shopify\ShopifyCollection;
use Drupal\neg_shopify\Settings;
use Drupal\neg_shopify\UserManagement;
use Drupal\neg_shopify\ShopifyCustomer;
use Drupal\neg_shopify\ShopifyVendors;
use Drupal\neg_shopify\Event\WebhookEvent;

/**
 * Class ShopifyWebhook.
 */
class ShopifyWebhook extends QueueWorkerBase {

  /**
   * Processes a queue item.
   */
  public function processItem($data) {

    $event = new WebhookEvent($data);
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $event_dispatcher->dispatch(WebhookEvent::PREPROCESS, $event);

    switch ($data['hook']) {

      // case 'orders/create':
      case 'orders/updated':
      case 'orders/cancelled':
        break;

      // case 'customers/create':
      case 'customers/update':
        $allowShopifyLogins = (BOOL) Settings::config()->get('allow_shopify_users');
        if (!$allowShopifyLogins) {
          break;
        }

        UserManagement::syncUserWithShopify($data['payload']);
        break;

      case 'customers/delete':
        $gid = ShopifyCustomer::idToGraphQlId($data['payload']['id']);
        $user = UserManagement::loadUserByShopifyId($gid);

        if ($user) {
          UserManagement::deleteUser($user);
        }
        break;

      // case 'products/create':
      case 'products/update':
        $product = ShopifyProduct::updateProduct($data['payload']);
        if ($product !== FALSE) {
          ShopifyVendors::syncVendors([
            $product->id(),
          ]);
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
          ShopifyVendors::syncVendors();
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

    $event_dispatcher->dispatch(WebhookEvent::POSTPROCESS, $event);
  }

}
