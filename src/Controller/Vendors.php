<?php

namespace Drupal\neg_shopify\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\neg_shopify\ShopifyVendors;

/**
 * Class Vendors.
 */
class Vendors extends ControllerBase {

  /**
   * Renders /vendors/{vendor} title.
   */
  public function getTitle($vendor) {
    return ShopifyVendors::fetchVendorNameBySlug($vendor);
  }

  /**
   * Renders /vendors/{vendor}.
   */
  public function render($vendor) {

    $build = [
      '#theme' => 'shopify-collection-all',
      '#name' => $this->getTitle($vendor),
    ];
    ShopifyVendors::renderProductsByVendorSlug($vendor, $build);
    return $build;
  }

}
