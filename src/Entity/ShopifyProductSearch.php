<?php

namespace Drupal\neg_shopify\Entity;

/**
 * Class ShopifyProductSearch.
 */
class ShopifyProductSearch {

  protected $params;

  /**
   * Implements __construct().
   */
  public function __construct($params = []) {
    $this->params = $params;
  }

  /**
   * Get's a count of all products in search.
   */
  public function count() {
    $query = $this->getQuery();
    return $query->count()->execute();
  }

  /**
   * Loads products based on search.
   */
  public function search(int $page = 0, int $perPage = 0) {
    $query = $this->getQuery();

    if ($perPage !== 0) {
      $query->range($page * $perPage, $perPage);
    }

    $ids = $query->execute();
    return ShopifyProduct::loadMultiple($ids);
  }

  /**
   * Gets a search query object.
   */
  protected function getQuery() {
    $params = $this->params;

    $query = \Drupal::entityQuery('shopify_product');

    // Require images for found products.
    $query->condition('image__target_id', NULL, 'IS NOT NULL');

    if (isset($params['collection_id'])) {
      $query->condition('collections', $params['collection_id']);
    }

    return $query;
  }

}
