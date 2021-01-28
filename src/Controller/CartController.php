<?php

namespace Drupal\neg_shopify\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;

use Drupal\Core\Cache\Cache;
use Drupal\neg_shopify\Settings;
use Drupal\neg_shopify\Entity\ShopifyProductVariant;
use Drupal\neg_shopify\StoreFrontService;

/**
 * Class CartController.
 */
class CartController extends ControllerBase {

  /**
   * Gets the cart object.
   */
  protected function getCart() {
    $store = \Drupal::service('tempstore.private')->get('neg_shopify');
    $cart = $store->get('cart');

    if ($cart === NULL || !isset($cart['items']) || !is_array($cart['items'])) {
      $cart = $this->newCart();
    }

    return $cart;
  }

  /**
   * Saves the cart object.
   */
  protected function saveCart($cart) {
    sort($cart['items']);
    $cart = $this->calculateCartTotals($cart);
    $store = \Drupal::service('tempstore.private')->get('neg_shopify');
    $cart = $store->set('cart', $cart);
    Cache::invalidateTags(['shopping_cart']);
    return TRUE;
  }

  /**
   * Calculates cart totals.
   */
  protected function calculateCartTotals($cart) {
    $total = 0;
    foreach ($cart['items'] as $item) {
      $variant = ShopifyProductVariant::loadByVariantId($item['variantId']);
      $price = (float) $variant->price->value * 100;
      $total += $price * (int) $item['quantity'];
    }
    $cart['total'] = $total;

    return $cart;
  }

  /**
   * Creates a new cart object.
   */
  protected function newCart() {
    return [
      'items' => [],
      'total' => 0,
    ];
  }

  /**
   * Finds an item in the cart.
   */
  protected function findItem($variantId) {
    $cart = $this->getCart();

    foreach ($cart['items'] as $index => $item) {
      if ($item['variantId'] === $variantId) {
        return $index;
      }
    }

    return FALSE;
  }

  /**
   * Removes an item.
   */
  protected function removeItem($variantId) {
    $index = $this->findItem($variantId);
    if ($index === FALSE) {
      return $this->renderError('Item not in cart!');
    }

    $cart = $this->getCart();
    unset($cart['items'][$index]);
    $this->saveCart($cart);

    return $this->renderCart([
      'nocache' => TRUE,
    ]);
  }

  /**
   * Adds an item to the cart.
   */
  protected function addItem($variantId, $quantity, $addToQuantity = TRUE) {

    $cart = $this->getCart();

    if ($variantId === NULL) {
      return $this->renderError('Variant ID must be set!');
    }

    if ($quantity === NULL) {
      return $this->renderError('Quantity must be set!');
    }

    $index = $this->findItem($variantId);
    $currentQuantity = ($index !== FALSE) ? $cart['items'][$index]['quantity'] : 0;

    if ($addToQuantity === TRUE) {
      $desiredQuantity = $currentQuantity + $quantity;
    }
    else {
      $desiredQuantity = $quantity;
    }

    if ($desiredQuantity == 0) {
      return $this->removeItem($variantId);
    }

    $item = [
      'variantId' => $variantId,
      'quantity' => $desiredQuantity,
    ];

    if ($index !== FALSE) {
      $cart['items'][$index] = $item;
    }
    else {
      $cart['items'][] = $item;
    }

    $this->saveCart($cart);

    $params = [
      'nocache' => TRUE,
    ];

    if ($addToQuantity === TRUE) {
      $params['redirectToCart'] = TRUE;
    }

    return $this->renderCart($params);
  }

  /**
   * Renders the cart.
   */
  public function render() {
    $build = [
      '#theme' => 'neg_shopify_cart',
      '#attached' => [
        'library' => [
          'neg_shopify/cart_controller',
          'negnet_utility/negnet-responsive-images'
        ],
      ],
      '#cache' => [
        'tags' => ['neg_shopify_cart_page'],
      ],
    ];

    Settings::attachShopifyJs($build);

    return $build;
  }

  /**
   * Stops the checkout.
   */
  protected function stopCheckout() {
    $cart = $this->getCart();
    unset($cart['checkoutStarted']);
    $this->saveCart($cart);

    return $this->renderCart([
      'nocache' => TRUE,
    ]);
  }

  /**
   * Resets the cart.
   */
  protected function resetCart() {
    $cart = $this->newCart();
    $this->saveCart($cart);

    return $this->renderCart([
      'nocache' => TRUE,
    ]);
  }

  /**
   * Renders cart json.
   */
  public function jsonEndpoint() {

    $request = \Drupal::request()->query->get('request');

    if ($request === NULL) {
      throw new NotFoundHttpException();
    }

    switch ($request) {

      case 'resetCart':
        return $this->resetCart();

      case 'stopCheckout':
        return $this->stopCheckout();

      case 'checkout':
        return $this->checkout();

      case 'render':
        return $this->rendercart();

      case 'remove':
        $variantId = \Drupal::request()->query->get('variantId');
        return $this->removeItem($variantId);

      case 'update':
        $variantId = \Drupal::request()->query->get('variantId');
        $qty = \Drupal::request()->query->get('qty');
        return $this->addItem($variantId, $qty, FALSE);

      case 'add':
        $variantId = \Drupal::request()->query->get('variantId');
        $qty = \Drupal::request()->query->get('qty');
        return $this->addItem($variantId, $qty);
    }

    throw new NotFoundHttpException();
  }

  /**
   * Updates a checkout.
   */
  protected function updateCheckout($checkoutId, $lineItems) {

    $query = <<<EOF
  mutation {
    checkoutLineItemsReplace(lineItems: {$lineItems}, checkoutId: "{$checkoutId}",
  ) {
      checkout {
         id
         webUrl
         completedAt
       }
    }
  }
EOF;

    try {
      $results = StoreFrontService::request($query);
      if (!isset($results['data']) || !isset($results['data']['checkoutLineItemsReplace']) || !isset($results['data']['checkoutLineItemsReplace']['checkout']) || !isset($results['data']['checkoutLineItemsReplace']['checkout']['id'])) {
        return FALSE;
      }
    }
    catch (\Exception $e) {
      return FALSE;
    }

    return $results['data']['checkoutLineItemsReplace']['checkout'];
  }

  /**
   * Gets a new checkout.
   */
  protected function createCheckout($lineItems) {
    $query = <<<EOF
mutation {
  checkoutCreate(input: {
    lineItems: {$lineItems}
  }) {
    checkout {
       id
       webUrl
       completedAt
     }
  }
}
EOF;
    try {
      $results = StoreFrontService::request($query);
      if (!isset($results['data']) || !isset($results['data']['checkoutCreate']) || !isset($results['data']['checkoutCreate']['checkout']) || !isset($results['data']['checkoutCreate']['checkout']['id'])) {
        return FALSE;
      }
    }
    catch (\Exception $e) {
      Settings::log($e->getMessage());
      return FALSE;
    }

    return $results['data']['checkoutCreate']['checkout'];
  }

  /**
   * Checks out the cart.
   */
  protected function checkout() {

    $cart = $this->getCart();

    if (count($cart['items']) === 0) {
      return $this->renderError('Your cart is empty!');
    }

    $lineItems = [];

    // Check for IDs.
    foreach ($cart['items'] as &$item) {
      if (!isset($item['id'])) {
        $variant = ShopifyProductVariant::loadByVariantId($item['variantId']);
        if ($variant === FALSE) {
          break;
        }

        // We need to fetch the storefront id.
        $item['id'] = $variant->getStoreFrontId();
      }

      $lineItems[] = [
        'variantId' => $item['id'],
        'quantity' => (int) $item['quantity'],
      ];
    }

    // Encode lineItems.
    $lineItems = preg_replace('/"([^"]+)"\s*:\s*/', '$1:', json_encode($lineItems));

    if (!isset($cart['checkout']['id'])) {
      // Create a new checkout.
      $checkout = $this->createCheckout($lineItems);
      if ($checkout === FALSE) {
        return $this->renderError('Could not create checkout! Please try again later!');
      }

      $cart['checkout'] = $checkout;
    }
    else {
      $checkoutId = $cart['checkout']['id'];

      // Update an existing.
      $checkout = $this->updateCheckout($checkoutId, $lineItems);
      if ($checkout === FALSE) {
        return $this->renderError('Could not update checkout! Please try again later!');
      }
    }

    // Mark checkout as started.
    $cart['checkoutStarted'] = TRUE;
    $this->saveCart($cart);

    return $this->renderCart([
      'nocache' => TRUE,
      'nocart' => TRUE,
      'redirect' => $checkout['webUrl'],
    ]);
  }

  /**
   * Render Error.
   */
  protected function renderError($msg) {
    return $this->renderCart([
      'status' => $msg,
    ]);
  }

  /**
   * Renders the cart.
   */
  protected function renderCart(array $params = []) {
    if (!isset($params['status'])) {
      $params['status'] = 'OK';
    }

    if (!isset($params['nocart'])) {
      $cart = $this->getCart();
      foreach ($cart['items'] as &$item) {
        $variant = ShopifyProductVariant::loadByVariantId($item['variantId']);
        if ($variant) {
          $view = $variant->loadView('cart', FALSE);
          $item['view'] = $view;
        }
        else {
          $view = '';
        }
      }
      $params['cart'] = $cart;
    }
    else {
      unset($params['nocart']);
    }

    $cache = [
      '#cache' => [
        'contexts' => ['session', 'url.query_args'],
        'tags' => ['shopping_cart'],
      ],
    ];

    if (isset($params['nocache'])) {
      $cache['#cache']['max-age'] = 0;
      unset($params['nocache']);
    }

    $response = new CacheableJsonResponse($params);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($cache));
    return $response;
  }

}
