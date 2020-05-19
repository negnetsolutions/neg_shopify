<?php

namespace Drupal\neg_shopify\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\neg_shopify\ShopifyCollection;

/**
 * Class Collections.
 */
class Collections extends ControllerBase {

  /**
   * Renders /collections/all.
   */
  public function renderAll() {
    $build = [
      '#theme' => 'shopify-collection-all',
      '#name' => 'Products',
    ];
    ShopifyCollection::renderAll($build);
    return $build;
  }

}
