<?php

namespace Drupal\neg_shopify\Entity;

use Drupal\views\EntityViewsData;
use Drupal\views\EntityViewsDataInterface;

/**
 * Provides Views data for Shopify product variant entities.
 */
class ShopifyVendorViewsData extends EntityViewsData implements EntityViewsDataInterface {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    $data['shopify_vendor']['table']['base'] = [
      'field' => 'id',
      'title' => $this->t('Shopify vendor'),
      'help' => $this->t('The Shopify vendor ID.'),
    ];

    return $data;
  }

}
