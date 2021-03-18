<?php

namespace Drupal\neg_shopify\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\neg_shopify\ShopifyVendors;

/**
 * Class Vendors.
 */
class VendorsController extends ControllerBase {

  /**
   * Renders /vendors title.
   */
  public function getVendorPageTitle() {
    return 'Vendors';
  }

  /**
   * Renders /vendors.
   */
  public function renderAll() {

    $build = [
      '#theme' => 'shopify_vendors_page',
    ];
    ShopifyVendors::renderVendorsPage($build);
    return $build;
  }

}
