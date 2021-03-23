<?php

namespace Drupal\neg_shopify\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Event that is fired when a user logs in.
 */
class WebhookEvent extends Event {

  const POSTPROCESS = 'neg_shopify_postprocess_webhook';
  const PREPROCESS = 'neg_shopify_preprocess_webhook';

  /**
   * The webhook event.
   *
   * @var array
   */
  public $hook;

  /**
   * Constructs the object.
   */
  public function __construct($hook) {
    $this->hook = $hook;
  }

}
