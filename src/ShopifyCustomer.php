<?php

namespace Drupal\neg_shopify;

use Drupal\neg_shopify\Api\StoreFrontService;
use Drupal\neg_shopify\Api\ShopifyService;
use Drupal\neg_shopify\Settings;

/**
 * Provides User data via shopify GRAPH api.
 */
class ShopifyCustomer {

  /**
   * The customer email.
   *
   * @var string
   */
  protected $email = FALSE;

  /**
   * The customer user.
   *
   * @var object
   */
  protected $user = FALSE;

  /**
   * The customer access token.
   *
   * @var string
   */
  protected $accessToken = FALSE;

  /**
   * Constructor.
   */
  public function __construct(array $params = []) {
    if (isset($params['accessToken'])) {
      $this->accessToken = $params['accessToken'];
    } else if (isset($params['email'])) {
      $this->email = $params['email'];
    } else if (isset($params['user'])) {
      $this->user = $params['user'];
      $this->email = $this->user->getEmail();
    }
  }

  /**
   * Update Users data with Shopify.
   */
  public function updateDrupalUser($drupalUser) {
    $info = $this->getCustomerInfo();

    $id = base64_decode($info['id']);
    $drupalUser->field_first_name->setValue(['value' => $info['firstName']]);
    $drupalUser->field_last_name->setValue(['value' => $info['lastName']]);
    $drupalUser->field_shopify_id->setValue(['value' => $id]);
    $drupalUser->save();
  }

  /**
   * Updates shopify user with user input.
   */
  public function updateShopifyUser(array $input) {

    $customerInput = [];
    foreach ($input as $key => $value) {
      $customerInput[] = "$key: \"$value\"";
    }
    $customerInputString = implode(', ', $customerInput);

    $accessToken = $this->accessToken;

    // Let's use the storefront api.
    if ($accessToken) {
      $query = <<<EOF
  mutation customerUpdate {
    customerUpdate(customerAccessToken: "${accessToken}", customer: { ${customerInputString} }) {
      customer {
        id
      }
      customerUserErrors {
        code
        field
        message
      }
    }
  }
EOF;
      $results = StoreFrontService::request($query);
      if (isset($results['data']['customerUpdate']['customer']['id']) && $results['data']['customerUpdate']['customer']['id'] !== NULL) {
        return TRUE;
      }
    }
    else {
      $id = $this->user->get('field_shopify_id')->value;
      if ($id === NULL) {
        throw new \Exception("Shopify ID missing!");
      }

      $query = <<<EOF
mutation customerUpdate {
  customerUpdate(input: { id: "${id}", ${customerInputString} }) {
    customer {
      id
    }
    userErrors {
      field
      message
    }
  }
}
EOF;

      $results = ShopifyService::instance()->graphQL($query);
      if (isset($results['data']['customerUpdate']['customer']['id']) && $results['data']['customerUpdate']['customer']['id'] !== NULL) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Get's customer info.
   */
  public function getCustomerInfo() {
    if ($this->email !== FALSE) {
      $email = $this->email;
      // Query using admin api.
      $query = <<<EOF
{
  customers(first: 1, query: "email:'{$email}'") {
    edges {
      node {
        id
        firstName
        lastName
      }
    }
  }
}
EOF;
      try {
        $results = ShopifyService::instance()->graphQL($query);
        if ($results['data']['customers'][0] !== NULL) {
          return $results['data']['customers'][0]['node'];
        }
      }
      catch (\Exception $e) {
      }

      return FALSE;
    }
    else {
      // Query with storefront api.
      if ($this->accessToken === FALSE) {
        throw new \Exception('AccessToken Required!');
      }

      $accessToken = $this->accessToken;
      $query = <<<EOF
{
	customer(customerAccessToken: "${accessToken}") {
  	  	id
      	firstName
        lastName
	}
}
EOF;
      try {
        $results = StoreFrontService::request($query);

        if (isset($results['data']['customer']) && $results['data']['customer'] !== NULL) {
          return $results['data']['customer'];
        }
      }
      catch (\Exception $e) {
      }

      return FALSE;
    }

    throw new \Exception('Customer required!');
  }

  /**
   * Get's order details.
   */
  public function getOrderDetails($orderId) {
    if ($this->isBase64Encoded($orderId)) {
      $orderId = base64_decode($orderId);
    }

    $query = <<<EOF
query {
  node(
    id: "{$orderId}"
  ) {
    ... on Order {
      name
      processedAt
      cancelledAt
      closedAt
      displayFinancialStatus
      displayFulfillmentStatus
      currentTaxLines {
        title
        ratePercentage
        priceSet {
          presentmentMoney {
            amount
            currencyCode
          }
        }
      }
      customer {
        displayName
      }
      billingAddress {
        formatted (withName: true)
      }
      shippingAddress {
        formatted (withName: true)
      }
      fulfillments {
        createdAt
        trackingInfo {
          company
          number
          url
        }
      }
      currentSubtotalPriceSet {
        presentmentMoney {
          amount
          currencyCode
        }
      }
      currentTotalDiscountsSet {
        presentmentMoney {
          amount
          currencyCode
        }
      }
      totalShippingPriceSet {
         presentmentMoney {
          amount
          currencyCode
        }
      }
      currentTotalPriceSet {
        presentmentMoney {
          amount
          currencyCode
        }
      }
      totalReceivedSet {
        presentmentMoney {
          amount
          currencyCode
        }
      }
      totalRefundedSet {
        presentmentMoney {
          amount
          currencyCode
        }
      }
      shippingLines(first: 50) {
        edges {
          node {
            carrierIdentifier
          }
        }
      }
      lineItems(first: 50) {
        edges {
          node {
            name
            vendor
            variantTitle
            sku
            quantity
            fulfillmentStatus
            originalTotalSet {
              presentmentMoney {
                amount
                currencyCode
              }
            }
            discountedTotalSet {
              presentmentMoney {
                amount
                currencyCode
              }
            }
            image {
              transformedSrc(scale: 2)
              height
              width
              altText
            }
          }
        }
      }
    }
  }
}
EOF;

    try {
      $results = ShopifyService::instance()->graphQL($query);
      if ($results['data']['node'] !== NULL) {
        return $results['data']['node'];
      }
    }
    catch (\Exception $e) {
    }

    return FALSE;
  }

  /**
   * Get's list of user orders.
   */
  public function getUserOrders(string $page = NULL, int $limit = 5, $direction = 'after') {
    return $this->getAdminUserOrders($page, $limit, $direction);
  }

  /**
   * Gets admin api orders.
   */
  protected function getAdminUserOrders(string $page = NULL, int $limit = 5, $direction = 'after') {
    $email = $this->email;
    $after = ($page === NULL) ? '' : ", $direction: \"$page\"";
    $first = ($direction === 'after') ? 'first' : 'last';

    $query = <<<EOF
{
  customers(first: 1, query: "email:'{$email}'") {
    edges {
      node {
        orders (reverse: true, sortKey: PROCESSED_AT, ${first}: ${limit}${after})  {
         edges {
          cursor
          node {
            id,
            name,
            processedAt,
            displayFinancialStatus,
            displayFulfillmentStatus,
            totalPriceSet {
              presentmentMoney {
                amount,
                currencyCode
              }
            }
          }
         }
         pageInfo {
          hasNextPage
          hasPreviousPage
         }
        }
      }
    }
  }
}
EOF;

    $nodes = [];
    $lastCursor = NULL;
    $pageInfo = NULL;

    try {
      $results = ShopifyService::instance()->graphQL($query);

      if (count($results['data']['customers']['edges']) > 0) {
        $lastCursor = NULL;
        $customer = $results['data']['customers']['edges'][0]['node'];
        $edges = $customer['orders']['edges'];

        foreach ($edges as $edge) {
          $lastCursor = $edge['cursor'];
          $node = $edge['node'];
          $nodes[] = [
            'id' => $node['id'],
            'orderNumber' => $node['name'],
            'processedAt' => $node['processedAt'],
            'fulfillmentStatus' => $node['displayFulfillmentStatus'],
            'financialStatus' => $node['displayFinancialStatus'],
            'totalPriceV2' => $node['totalPriceSet']['presentmentMoney'],
          ];
        }

        $pageInfo = $customer['orders']['pageInfo'];
      }

    }
    catch (\Exception $e) {
    }

    return [
      'orders' => $nodes,
      'lastCursor' => $lastCursor,
      'pageInfo' => $pageInfo,
    ];
  }

  /**
   * Renders order history block.
   */
  public function renderOrderHistoryBlock($perPage = 5) {
    $build = [
      '#theme' => 'neg_shopify_user_order_history',
      '#email' => $this->email,
      '#perPage' => $perPage,
      '#attached' => [
        'library' => [
          'neg_shopify/user_order_history_block',
        ],
      ],
      '#cache' => [
        'content' => ['user', 'url.query_args'],
      ],
    ];

    if ($this->user) {
      $build['#cache']['tags'] = $this->user->getCacheTags();
    }
    else {
      $build['#cache']['max-age'] = 30;
    }

    Settings::attachShopifyJs($build);

    return $build;
  }

  /**
   * Detects if a string is base64 encoded.
   */
  private function isBase64Encoded(string $s) : bool {
    if ((bool) preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $s) === false) {
      return false;
    }
    $decoded = base64_decode($s, true);
    if ($decoded === false) {
      return false;
    }
    $encoding = mb_detect_encoding($decoded);
    if (! in_array($encoding, ['UTF-8', 'ASCII'], true)) {
      return false;
    }
    return $decoded !== false && base64_encode($decoded) === $s;
  }
}
