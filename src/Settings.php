<?php

namespace Drupal\neg_shopify;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Url;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\neg_shopify\Api\ShopifyService;

/**
 * Class Settings.
 */
class Settings {

  const CONFIGNAME = 'neg_shopify.settings';
  const WEBHOOKQUEUE = 'neg_shopify_webhook';
  const CRONQUEUE = 'neg_shopify';
  const COLLECTIONSQUEUE = 'neg_shopify_collections';
  const USERSQUEUE = 'neg_shopify_users';
  const DEFAULT_SORT = 'date-descending';
  const API_VERSION = '2021-01';

  /**
   * Invalidates product cache.
   */
  public static function invalidateCache() {
    Cache::invalidateTags(['shopify_product']);
  }

  /**
   * Gets the webhook route urls.
   */
  public static function webhookRouteUrl() {
    return Url::fromRoute('neg_shopify.webhook')->setAbsolute()->toString();
  }

  /**
   * Gets store url.
   */
  public static function storeUrl($display = 'main', $arg = NULL) {
    return 'https://' . self::shopInfo('domain');
  }

  /**
   * Gets currency format.
   */
  public static function currencyFormat($amount) {
    return strtr(self::shopInfo()->money_format, ['{{amount}}' => $amount]);
  }

  /**
   * Gets default google product category.
   */
  public static function defaultGoogleProductCategory() {
    return self::config()->get('google_product_category');
  }

  /**
   * Gets shop info.
   */
  public static function shopInfo($key = '', $refresh = FALSE) {
    if ($refresh) {
      $info = ShopifyService::instance()->shopInfo();

      // Convert to object.
      $info = json_decode(json_encode($info), FALSE);
      \Drupal::state()->set('shopify.shop_info', $info);
    }
    $info = \Drupal::state()->get('shopify.shop_info', []);
    if (!empty($key)) {
      return isset($info->{$key}) ? $info->{$key} : '';
    }
    else {
      return $info;
    }
  }

  /**
   * Emptys a queue.
   */
  public static function emptyQueue($queue) {
    while ($item = $queue->claimItem()) {
      try {
        $queue->deleteItem($item);
      }
      catch (SuspendQueueException $e) {
        $queue->releaseItem($item);
        break;
      }
      catch (\Exception $e) {
        self::log($e->getMessage());
      }
    }
  }

  /**
   * Get Webhook Queue Worker.
   */
  public static function webhookQueueWorker() {
    $queue_manager = \Drupal::service('plugin.manager.queue_worker');
    $worker = $queue_manager->createInstance(self::WEBHOOKQUEUE);
    return $worker;
  }

  /**
   * Get Webhook Queue.
   */
  public static function webhookQueue() {
    $queue_factory = \Drupal::service('queue');
    $queue = $queue_factory->get(self::WEBHOOKQUEUE);
    return $queue;
  }

  /**
   * Get Collections Queue.
   */
  public static function collectionsQueue() {
    $queue_factory = \Drupal::service('queue');
    $queue = $queue_factory->get(self::COLLECTIONSQUEUE);
    return $queue;
  }

  /**
   * Get Users Queue.
   */
  public static function usersQueue() {
    $queue_factory = \Drupal::service('queue');
    $queue = $queue_factory->get(self::USERSQUEUE);
    return $queue;
  }

  /**
   * Get Queue.
   */
  public static function queue() {
    $queue_factory = \Drupal::service('queue');
    $queue = $queue_factory->get(self::CRONQUEUE);
    return $queue;
  }

  /**
   * Logs a message.
   */
  public static function log($message, $params = [], $log_level = 'notice') {
    \Drupal::logger('neg_shopify')->$log_level($message, $params);
  }

  /**
   * Adds shopify js.
   */
  public static function attachShopifyJs(&$build) {
    $build['#attached']['library'][] = 'neg_shopify/shopify.js';
    $build['#attached']['drupalSettings']['cart'] = [
      'endpoint' => Url::fromRoute('neg_shopify.cart.json')->toString(),
      'cartPage' => Url::fromRoute('neg_shopify.cart')->toString(),
      'emptyRedirect' => '/collections/all',
    ];
  }

  /**
   * Gets the store front api key.
   */
  public static function accessToken() {
    $config = self::config();
    return $config->get('store_front_access_token');
  }

  /**
   * Gets products label.
   */
  public static function productsLabel() {
    $config = self::config();
    $label = $config->get('products_label');
    if ($label === NULL) {
      return 'products';
    }

    return $label;
  }

  /**
   * Gets default sort order.
   */
  public static function defaultSortOrder() {
    return self::DEFAULT_SORT;
  }

  /**
   * Gets products per page.
   */
  public static function productsPerPage() {
    return self::config()->get('products_per_page');
  }

  /**
   * Gets a config object.
   */
  public static function config() {
    return \Drupal::config(self::CONFIGNAME);
  }

  /**
   * Gets an editable config object.
   */
  public static function editableConfig() {
    return \Drupal::service('config.factory')->getEditable(self::CONFIGNAME);
  }

}
