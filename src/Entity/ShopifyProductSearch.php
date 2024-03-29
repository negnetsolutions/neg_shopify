<?php

namespace Drupal\neg_shopify\Entity;

use Drupal\neg_shopify\Settings;
use Drupal\neg_shopify\SortArrayByProductId;
use Drupal\neg_shopify\Entity\ShopifyProduct;

/**
 * Class ShopifyProductSearch.
 */
class ShopifyProductSearch {

  /**
   * {@inheritdoc}
   */
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

    $nodes = (count($ids) > 0) ? ShopifyProduct::loadMultiple($ids) : [];

    // See if there is a preset sort_order.
    if (isset($this->params['sort']) && $this->params['sort'] === 'manual-ascending' && isset($this->params['collection_sort']) && isset($this->params['collection_sort']['sort_order']) && $this->params['collection_sort']['sort_order'] === 'manual' && isset($this->params['collection_sort']['items'])) {
      $this->sortByProductId($nodes, $this->params['collection_sort']['items']);
    }

    return $nodes;
  }

  /**
   * Sorts ids by their manual sort order.
   */
  protected function sortByProductId(array &$nodes, array $sortProductIds) {
    usort($nodes, [new SortArrayByProductId($sortProductIds), 'call']);
  }

  /**
   * Gets a search query object.
   */
  protected function getQuery() {
    $params = $this->params;

    $query = \Drupal::entityQuery('shopify_product');

    // Require images for found products.
    $query->condition('image__target_id', NULL, 'IS NOT NULL');

    // Require published product to this channel.
    $query->condition('published_at', time(), '<=');

    // Check if user can see all products.
    if (!isset($params['show']) && \Drupal::currentUser()->hasPermission('view unavailable shopify product entities')) {
      $params['show'] = 'all';
    }

    // Filter for max-price.
    if (isset($params['max-price'])) {
      $query->condition('low_price', $params['max-price'], '<');
    }

    // Filter for max-price.
    if (isset($params['min-price'])) {
      $query->condition('low_price', $params['min-price'], '>');
    }

    $show = isset($params['show']) ? $params['show'] : 'available';

    if ($show === 'available') {
      // Require active product.
      $query->condition('status', TRUE);

      // Add an or query for available or preorders.
      $group = $query->orConditionGroup();

      // Make sure is available.
      $group->condition('is_available', TRUE);

      // Or is a preorder.
      $group->condition('is_preorder', TRUE);

      $query->condition($group);

      // Only show from published vendors.
      $query->addTag('publishedvendors');
    }

    if (isset($params['tags'])) {
      foreach ($params['tags'] as $tag) {
        if (strlen($tag) > 0) {
          $termManager = \Drupal::entityTypeManager()
            ->getStorage('taxonomy_term');
          $andTags = explode('-and-', $tag);

          if (count($andTags) > 1) {
            $group = $query->andConditionGroup();
            foreach ($andTags as $tag) {
              $tagResults = $termManager->getQuery()
                ->condition('vid', 'shopify_tags', '=')
                ->condition('name', "%$tag%", 'LIKE')
                ->range(0, 1)
                ->execute();
              $tagResults = (count($tagResults) > 0) ? $tagResults : [0];
              $group->condition('tags', $tagResults, 'IN');
            }
            $query->condition($group);
          }
          elseif (count($orTags = explode('-or-', $tag)) > 1) {
            $group = $query->orConditionGroup();
            foreach ($andTags as $tag) {
              $tagResults = $termManager->getQuery()
                ->condition('vid', 'shopify_tags', '=')
                ->condition('name', "%$tag%", 'LIKE')
                ->range(0, 1)
                ->execute();
              $tagResults = (count($tagResults) > 0) ? $tagResults : [0];
              $group->condition('tags', $tagResults, 'IN');
            }
            $query->condition($group);
          }
          else {
            $tagResults = $termManager->getQuery()
              ->condition('vid', 'shopify_tags', '=')
              ->condition('name', "%$tag%", 'LIKE')
              ->range(0, 1)
              ->execute();
            $tagResults = (count($tagResults) > 0) ? $tagResults : [0];
            $query->condition('tags', array_values($tagResults), 'IN');
          }
        }
      }
    }

    if (isset($params['vendor_name'])) {
      $query->condition('vendor', "%{$params['vendor_name']}%", 'LIKE');
    }

    if (isset($params['vendor_slug'])) {
      $query->condition('vendor_slug', $params['vendor_slug']);
    }

    // Smart Collection.
    if (isset($params['collection_sort']) && isset($params['collection_sort']['rules'])) {
      // Set group type.
      $rules_group = $query->andConditionGroup();
      if ($params['collection_disjunctive']) {
        $rules_group = $query->orConditionGroup();
      }

      foreach ($params['collection_sort']['rules'] as $rule) {
        $field = FALSE;
        $relation = '=';

        switch ($rule['column']) {
          case 'is_price_reduced':
            $invert = ($rule['relation'] == 'is_set') ? FALSE : TRUE;
            $field = 'variants.entity:shopify_product_variant.compare_at_price';
            $rule['condition'] = ($invert) ? '' : 0;
            $rule['relation'] = ($invert) ? '=' : 'greater_than';
            break;

          case 'title':
            $field = $rule['column'];
            break;

          case 'vendor':
            $field = $rule['column'];
            break;

          case 'type':
            $field = 'product_type';
            break;

          case 'tag':
            $field = 'tags.entity:taxonomy_term.name';
            break;

          case 'variant_title':
            $field = 'variants.entity:shopify_product_variant.title';
            break;

          case 'variant_compare_at_price':
            $field = 'variants.entity:shopify_product_variant.compare_at_price';
            break;

          case 'variant_weight':
            $field = 'variants.entity:shopify_product_variant.weight';
            break;

          case 'variant_inventory':
            $field = 'variants.entity:shopify_product_variant.inventory_quantity';
            break;

          case 'variant_price':
            $field = 'variants.entity:shopify_product_variant.price';
            break;
        }

        switch ($rule['relation']) {
          case 'equals':
            $relation = '=';
            break;

          case 'not_equals':
            $relation = '<>';
            break;

          case 'greater_than':
            $relation = '>';
            break;

          case 'less_than':
            $relation = '<';
            break;

          case 'starts_with':
            $relation = 'STARTS_WITH';
            break;

          case 'ends_with':
            $relation = 'ENDS_WITH';
            break;

          case 'contains':
            $relation = 'CONTAINS';
            break;
        }

        if ($field) {
          $rules_group->condition($field, $rule['condition'], $relation);
        }
      }

      $query->condition($rules_group);
    }

    // Custom Collection.
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

    $query->addTag('sort_by_is_available');

    foreach ($sort as $option) {
      $query->sort($option, $direction);
    }

    return $query;
  }

  /**
   * Renders a json response for a search.
   */
  public static function renderJson($sortOrder = FALSE, $page = 0, $perPage = FALSE, $tags = [], $vendors = []) {
    $params = [
      'tags' => $tags,
      'sort' => $sortOrder,
    ];

    if (count($vendors) > 0) {
      $params['vendor_name'] = $vendors[0];
    }

    $search = new self($params);
    $products = $search->search($page, $perPage);
    $total = $search->count();

    return [
      'count' => $total,
      'items' => ShopifyProduct::loadView($products, 'store_listing', FALSE),
    ];
  }
}
