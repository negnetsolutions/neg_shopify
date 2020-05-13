<?php

namespace Drupal\neg_shopify;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Url;

/**
 * Class Settings.
 */
class Settings {

  const CONFIGNAME = 'neg_shopify.settings';
  const WEBHOOKQUEUE = 'neg_shopify_webhook';
  const CRONQUEUE = 'neg_shopify';

  /**
   * Invalidates review cache.
   */
  public static function invalidateCache() {
    Cache::invalidateTags(['shopify_product']);
  }

  /**
   * Gets the webhook route urls.
   */
  public static function webhookRouteUrl() {
    // TODO.
    return 'https://d0a1489b.ngrok.io/shopify/webhook';
    // return Url::fromRoute('neg_shopify.webhook', ['absolute' => TRUE])->setAbsolute()->toString();
  }

  /**
   * Gets store url.
   */
  public static function storeUrl($display = 'main', $arg = NULL) {
    $view = View::load('shopify_store');
    if ($view instanceof View) {
      $path = $view->getDisplay($display)['display_options']['path'];
      if ($arg) {
        return strtr($path, ['%' => $arg]);
      }
      return $path;
    }
  }

  /**
   * Gets currency format.
   */
  public static function currencyFormat($amount) {
    return strtr(self::shopInfo()->money_format, ['{{amount}}' => $amount]);
  }

  /**
   * Gets shop info.
   */
  public static function shopInfo($key = '', $refresh = FALSE) {
    if ($refresh) {
      $info = ShopifyService::instance()->shopInfo();
      \Drupal::state()->set('shopify.shop_info', $info);
    }
    $info = \Drupal::state()->get('shopify.shop_info', new \stdClass());
    if (!empty($key)) {
      return isset($info->{$key}) ? $info->{$key} : '';
    }
    else {
      return $info;
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
