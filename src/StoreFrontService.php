<?php

namespace Drupal\neg_shopify;

/**
 * Shopify Storefront Service.
 */
class StoreFrontService {

  const API_VERSION = '2020-04';

  /**
   * Requests data from storefront.
   */
  public static function request(string $graphQL) {
    $client = \Drupal::httpClient();

    $headers = [
      'Accept' => 'application/json',
      'Content-Type' => 'application/graphql',
      'X-Shopify-Storefront-Access-Token' => Settings::accessToken(),
    ];

    $config = Settings::config();

    $path = 'https://' . $config->get('shop_url') . '.myshopify.com/api/' . self::API_VERSION . '/graphql.json';

    $request = $client->post($path, ['headers' => $headers, 'body' => $graphQL]);

    $response = json_decode($request->getBody(), TRUE);

    if (isset($response['errors'])) {
      throw new \Exception('GraphQL Error: ' . print_r($response['errors'], TRUE) . "\nGRAPHQL: $graphQL");
    }

    return $response;
  }

}
