<?php

namespace Drupal\neg_shopify\Entity;

use Drupal\neg_shopify\Settings;

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

    // Make sure is available.
    $query->condition('is_available', TRUE);

    if (isset($params['collection_id'])) {
      $query->condition('collections', $params['collection_id']);
    }

    if (isset($params['limit'])) {
      $query->range(0, $params['limit']);
    }

    // Set sort order.
    if (isset($params['sort'])) {
      $sort = $params['sort'];
    }
    else {
      $sort = Settings::defaultSortOrder();
    }

    $parts = explode('-', $sort);

    // Default.
    $sort = ['title'];

    if (isset($parts[0])) {
      switch ($parts[0]) {
        case 'price':
          $sort = ['low_price', 'title'];
          break;

        case 'date':
          $sort = ['created_at', 'title'];
          break;

        case 'title':
          $sort = ['title'];
          break;
      }
    }

    // Default.
    $direction = 'DESC';
    if (isset($parts[1])) {
      switch ($parts[1]) {
        case 'ascending':
          $direction = 'ASC';
          break;

        case 'descending':
          $direction = 'DESC';
          break;
      }
    }

    foreach ($sort as $option) {
      $query->sort($option, $direction);
    }

    return $query;
  }

}
