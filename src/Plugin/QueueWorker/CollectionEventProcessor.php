<?php

namespace Drupal\neg_shopify\Plugin\QueueWorker;

/**
 *
 * @QueueWorker(
 * id = "neg_shopify_collections",
 * title = "Shopify Collection Queue",
 * cron = {"time" = 60}
 * )
 */
class CollectionEventProcessor extends SyncShopify {
}
