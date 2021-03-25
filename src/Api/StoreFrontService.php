<?php

namespace Drupal\neg_shopify\Api;

use Drupal\neg_shopify\Settings;

/**
 * Shopify Storefront Service.
 */
class StoreFrontService {

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

    $path = 'https://' . $config->get('shop_url') . '.myshopify.com/api/' . Settings::API_VERSION . '/graphql.json';

    $request = $client->post($path, ['headers' => $headers, 'body' => $graphQL]);

    $response = json_decode($request->getBody(), TRUE);

    if (isset($response['errors'])) {
      throw new GraphQlException($response['errors'], $graphQL);
    }

    return $response;
  }

  /**
   * Authenticates a customer with shopify.
   */
  public function authenticateUser($username, $password) {
    $query = <<<EOF
mutation customerAccessTokenCreate {
  customerAccessTokenCreate(input: { email: "{$username}", password: "{$password}" }) {
    customerUserErrors {
      code
      field
      message
    }
    customerAccessToken {
      accessToken
      expiresAt
    }
  }
}
EOF;

    $results = self::request($query);

    if (isset($results['data']['customerAccessTokenCreate']['customerAccessToken']) && $results['data']['customerAccessTokenCreate']['customerAccessToken'] !== NULL) {
      // Successful Shopify Login.
      return $results['data']['customerAccessTokenCreate']['customerAccessToken'];
    }

    if (isset($results['data']['customerAccessTokenCreate']['customerUserErrors'])) {
      throw new GraphQlException($results['data']['customerAccessTokenCreate']['customerUserErrors'], $query);
    }

    return FALSE;
  }

}
