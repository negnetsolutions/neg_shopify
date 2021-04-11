<?php

namespace Drupal\neg_shopify\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Event that is fired when multiple products have been loaded.
 */
class VendorSearchQueryEvent extends Event {

  const ALTERSEARCHQUERY = 'neg_shopify_postprocess_vendorsearchqueryevent';

  /**
   * The query interface.
   *
   * @var object
   */
  public $query;

  /**
   * Constructs the object.
   */
  public function __construct(&$query) {
    $this->query = &$query;
  }

}
