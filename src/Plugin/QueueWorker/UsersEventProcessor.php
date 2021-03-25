<?php

namespace Drupal\neg_shopify\Plugin\QueueWorker;

/**
 *
 * @QueueWorker(
 * id = "neg_shopify_users",
 * title = "Shopify Users Queue",
 * cron = {"time" = 60}
 * )
 */
class UsersEventProcessor extends SyncShopify {
}
