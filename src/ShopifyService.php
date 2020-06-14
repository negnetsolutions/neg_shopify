<?php

namespace Drupal\neg_shopify;

use PHPShopify\ShopifySDK;

/**
 * Shopify Service.
 */
class ShopifyService {

  protected static $instance = FALSE;
  protected $client;
  protected $productService = FALSE;
  protected $metafieldService = FALSE;
  protected $smartCollectionService = FALSE;
  protected $customCollectionService = FALSE;
  protected $webhookService = FALSE;
  protected $collectionProductsServices = [];

  /**
   * Gets the service instance.
   */
  public static function instance() {
    if (self::$instance === FALSE) {
      self::$instance = new self();
    }

    return self::$instance;
  }

  /**
   * Gets the client service.
   */
  public function client() {
    return $this->client;
  }

  /**
   * Implemenets __construct.
   */
  public function __construct() {

    $config = Settings::config();

    ShopifySDK::config([
      'ShopUrl' => $config->get('shop_url') . '.myshopify.com',
      'ApiKey' => $config->get('api_key'),
      'Password' => $config->get('api_password'),
    ]);

    $this->client = new ShopifySDK();
    $this->productService = $this->client->Product;
    $this->metafieldService = $this->client->Metafield;
    $this->smartCollectionService = $this->client->SmartCollection;
    $this->customCollectionService = $this->client->CustomCollection;
    $this->webhookService = $this->client->Webhook;
  }

  /**
   * Processes a page cursor into params.
   */
  protected function processPageCursor(string $cursor) {
    $query = parse_url($cursor, PHP_URL_QUERY);
    $params = [];
    parse_str($query, $params);
    return $params;
  }

  /**
   * Gets the last updated date.
   */
  public static function getLastProductUpdatedDate() {
    $ts = \Drupal::state()->get('neg_shopify.last_product_sync', 0);

    return self::formatTimestamp($ts);
  }

  /**
   * Gets the last updated date.
   */
  public static function getLastCollectionUpdatedDate() {
    $ts = \Drupal::state()->get('neg_shopify.last_collection_sync', 0);
    return self::formatTimestamp($ts);
  }

  /**
   * Formats timestamp to Shopify date format.
   */
  public static function formatTimestamp(int $timestamp) {
    return date('c', $timestamp);
  }

  /**
   * Fetchs shop info.
   */
  public function shopInfo() {
    return $this->client->Shop->get();
  }

  /**
   * Fetchs Collection Products IDs.
   */
  public function fetchCollectionProducts(int $collection_id, array $params = ['fields' => 'id']) {

    if (!isset($this->collectionProductsServices[$collection_id])) {
      $this->collectionProductsServices[$collection_id] = $this->client->Collection($collection_id)->Product;
    }

    $products = $this->collectionProductsServices[$collection_id]->get($params);

    // Get Next Page.
    $next = $this->collectionProductsServices[$collection_id]->getNextLink();
    if ($next !== NULL) {
      $products = array_merge($products, $this->fetchCollectionProducts($collection_id, $this->processPageCursor($next)));
    }

    return $products;
  }

  /**
   * Deletes a webhook.
   */
  public function deleteWebhook($id) {
    try {
      return $this->client->Webhook($id)->delete();
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addError($e->getMessage(), TRUE);
      return FALSE;
    }
  }

  /**
   * Creates a new webhook.
   */
  public function createWebhook($topic) {
    $params = [
      'topic' => $topic,
      'address' => Settings::webhookRouteUrl(),
      'format' => 'json',
    ];

    try {
      return $this->webhookService->post($params);
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addError($e->getMessage(), TRUE);
      return FALSE;
    }
  }

  /**
   * Gets webhooks.
   */
  public function getWebhooks($params = []) {

    if (count($params) === 0) {
      $params = [
        'limit' => 250,
      ];
    }

    $hooks = $this->webhookService->get($params);

    $next = $this->webhookService->getNextLink();
    if ($next !== NULL) {
      $hooks = array_merge($hooks, $this->getWebhooks($this->processPageCursor($next)));
    }

    return $hooks;
  }

  /**
   * Gets Collections.
   */
  public function fetchCollections(array $options = [], $type = 'both') {
    $smart_collections = $custom_collections = [];
    $pages = [];

    if ($type == 'both' || $type == 'smart') {
      $smart_collections = $this->smartCollectionService->get($options);
      $next = $this->smartCollectionService->getNextLink();
      if ($next !== NULL) {
        $smart_collections = array_merge($smart_collections, $this->fetchCollections($this->processPageCursor($next), 'smart'));
      }
    }
    if ($type == 'both' || $type == 'custom') {
      $custom_collections = $this->customCollectionService->get($options);
      $next = $this->customCollectionService->getNextLink();
      if ($next !== NULL) {
        $custom_collections = array_merge($custom_collections, $this->fetchCollections($this->processPageCursor($next), 'custom'));
      }
    }

    return array_merge($smart_collections, $custom_collections);
  }

  /**
   * Gets all products.
   */
  public function fetchAllProducts(array $params = []) {
    $products = [];
    $pages = $this->fetchAllPagedProducts($params);
    foreach ($pages as $page) {
      foreach ($page as $product) {
        $products[] = $product;
      }
    }

    return $products;
  }

  /**
   * Gets all products.
   */
  public function fetchAllPagedProducts(array $params = []) {

    $pages = [];

    // Fetch this page.
    $products = $this->productService->get($params);
    $pages[] = $products;

    // Get Next Page.
    $next = $this->productService->getNextLink();
    if ($next !== NULL) {
      $pages = array_merge($pages, $this->fetchAllPagedProducts($this->processPageCursor($next)));
    }

    return $pages;
  }

}
