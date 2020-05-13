<?php

namespace Drupal\neg_shopify\Plugin\QueueWorker;

/**
 *
 * @QueueWorker(
 * id = "neg_shopify_webhook",
 * title = "Shopify Webhook Queue",
 * cron = {"time" = 60}
 * )
 */
class WebhookEventProcessor extends ShopifyWebhook {
}
