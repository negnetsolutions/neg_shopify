<?php

namespace Drupal\neg_shopify\Plugin\QueueWorker;

/**
 *
 * @QueueWorker(
 * id = "neg_shopify",
 * title = "Shopify Product Queue",
 * cron = {"time" = 60}
 * )
 */
class CronEventProcessor extends SyncShopify {
}
