<?php

namespace Drupal\neg_shopify\Plugin\QueueWorker;

/**
 *
 * @QueueWorker(
 * id = "neg_shopify",
 * title = "Shopify Queue",
 * cron = {"time" = 60}
 * )
 */
class CronEventProcessor extends SyncShopify {
}
