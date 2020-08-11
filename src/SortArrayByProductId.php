<?php

namespace Drupal\neg_shopify;

/**
 * Class SortArrayByProductId.
 */
class SortArrayByProductId {

  /**
   * {@inheritdoc}
   */
  private $sortProductIds;

  /**
   * {@inheritdoc}
   */
  public function __construct($sortProductIds) {
    $this->sortProductIds = $sortProductIds;
  }

  /**
   * {@inheritdoc}
   */
  public function call($a, $b) {
    $aIndex = array_search($a->get('product_id')->value, $this->sortProductIds);
    $bIndex = array_search($b->get('product_id')->value, $this->sortProductIds);

    return ($aIndex < $bIndex) ? -1 : 1;
  }

}
