<?php

namespace Drupal\neg_shopify\Plugin;

use Drupal\neg_shopify\ShopifyService;
use Drupal\neg_shopify\Settings;

/**
 * Webhook Class.
 */
class Webhooks {

  /**
   * Gets Installed webhooks.
   */
  public static function getWebhooks() {
    $hooks = ShopifyService::instance()->getWebhooks();
    return $hooks;
  }

  /**
   * Gets webhook data table headers.
   */
  public static function getWebhooksDataTableHeaders() {
    return [
      'Event',
      'Url',
      'API Version',
      'Relevant',
    ];
  }

  /**
   * Gets webhook data table.
   */
  public static function getWebhooksData() {
    $data = [];
    $hooks = self::getWebhooks();

    foreach ($hooks as $hook) {
      $data[] = [
        $hook['topic'],
        $hook['address'],
        $hook['api_version'],
        ($hook['address'] === Settings::webhookRouteUrl()) ? 'YES' : 'NO',
      ];
    }
    return $data;
  }

  /**
   * Filters out relevent hooks.
   */
  public static function filterWebhooks($hooks, $filterRelevent = TRUE) {

    // Filter hooks by Url.
    foreach ($hooks as $i => $hook) {
      if ($filterRelevent === TRUE) {
        if ($hook['address'] !== Settings::webhookRouteUrl()) {
          array_splice($hooks, $i, 1);
        }
      }
      else {
        if ($hook['address'] === Settings::webhookRouteUrl()) {
          array_splice($hooks, $i, 1);
        }
      }
    }

    return $hooks;
  }

  /**
   * Uninstall Relevant webhooks.
   */
  public static function uninstallRelevantWebhooks() {
    $hooks = self::getWebhooks();

    foreach ($hooks as $hook) {
      if ($hook['address'] === Settings::webhookRouteUrl()) {
        ShopifyService::instance()->deleteWebhook($hook['id']);
      }
    }
  }

  /**
   * Uninstalls webhooks.
   */
  public static function uninstallWebhooks() {
    $hooks = self::getWebhooks();

    foreach ($hooks as $hook) {
      ShopifyService::instance()->deleteWebhook($hook['id']);
    }
  }

  /**
   * Installs webhooks.
   */
  public static function installWebhooks() {
    ShopifyService::instance()->createWebhook('products/create');
    ShopifyService::instance()->createWebhook('products/update');
    ShopifyService::instance()->createWebhook('products/delete');
    ShopifyService::instance()->createWebhook('collections/create');
    ShopifyService::instance()->createWebhook('collections/update');
    ShopifyService::instance()->createWebhook('collections/delete');
  }

}
