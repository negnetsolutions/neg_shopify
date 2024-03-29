<?php

namespace Drupal\neg_shopify\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\user\Entity\User;

use Drupal\neg_shopify\ShopifyCollection;
use Drupal\neg_shopify\Entity\ShopifyVendor;
use Drupal\neg_shopify\Entity\ShopifyProductSearch;
use Drupal\neg_shopify\ShopifyCustomer;
use Drupal\neg_shopify\ShopifyVendors;
use Drupal\neg_shopify\Settings;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\neg_shopify\Event\UserDataAuthorizeEvent;

/**
 * Class JsonController.
 */
class JsonController extends ControllerBase {

  /**
   * Renders user receipt.
   */
  public function userOrder() {
    $order = \Drupal::request()->query->get('order');
    if ($order === NULL) {
      throw new NotFoundHttpException();
    }

    $current_user = \Drupal::currentUser();

    if (!$current_user->hasPermission('view own shopify customer data')) {
      throw new NotFoundHttpException();
    }

    $customer = new ShopifyCustomer();
    $uid = $current_user->getAccount()->id();
    $user = User::load($uid);

    $data = $customer->getOrderDetails($order);

    $build = [
      '#theme' => 'neg_shopify_order_receipt',
      '#order' => $data,
      '#attached' => [
        'library' => [
          'neg_shopify/order_receipt',
        ],
      ],
      '#cache' => [
        'contexts' => ['user', 'url.query_args'],
        'tags' => $user->getCacheTags(),
      ],
    ];

    return $build;
  }

  /**
   * Checks access.
   */
  public function userOrdersAccess(AccountInterface $account) {

    $email = \Drupal::request()->query->get('email');
    $accountEmail = $account->getEmail();

    if ($email === NULL) {
      $email = $accountEmail;
    }

    $result = AccessResult::neutral();

    if ($accountEmail === $email) {
      $result = AccessResult::allowedIfHasPermission($account, 'view own shopify customer data');
    }
    else {
      $result = AccessResult::allowedIfHasPermission($account, 'view all shopify customer data');
    }

    $event = new UserDataAuthorizeEvent($account, $result);
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $event_dispatcher->dispatch(UserDataAuthorizeEvent::USERORDERSACCESS, $event);

    // Don't allow neutral results.
    if ($result->isNeutral()) {
      return AccessResult::forbidden();
    }

    return $result;
  }

  /**
   * Renders user orders.
   */
  public function userOrders() {
    $page = \Drupal::request()->query->get('page');
    $perPage = \Drupal::request()->query->get('per-page');
    $email = \Drupal::request()->query->get('email');
    $direction = \Drupal::request()->query->get('direction');

    if ($direction === NULL) {
      $direction = 'after';
    }

    if ($page === '0' || !is_numeric($page) || $page < 0) {
      $page = NULL;
    }

    if ($perPage === NULL || !is_numeric($perPage) || $perPage < 0) {
      $perPage = 5;
    }

    $current_user = \Drupal::currentUser();

    if ($email === NULL) {
      $email = $current_user->getEmail();
    }

    $customer = new ShopifyCustomer([
      'email' => $email,
    ]);

    $data = $customer->getUserOrders($page, $perPage, $direction);

    $cache = [
      '#cache' => [
        'contexts' => ['user', 'url.query_args'],
        'max-age' => 30,
      ],
    ];

    $response = new CacheableJsonResponse($data);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($cache));
    return $response;
  }

  /**
   * Renders products.
   */
  public function products() {

    $type = \Drupal::request()->query->get('type');

    if ($type === NULL) {
      throw new NotFoundHttpException();
    }

    $data = [];

    $page = \Drupal::request()->query->get('page');
    $perPage = \Drupal::request()->query->get('perpage');
    $perPage = ($perPage === NULL) ? Settings::productsPerPage() : $perPage;
    $sortOrder = \Drupal::request()->query->get('sort');
    $sortOrder = ($sortOrder === NULL) ? Settings::defaultSortOrder() : $sortOrder;
    $tags = [];

    if (!is_numeric($page) || $page < 0) {
      $page = 0;
    }

    if (!is_numeric($perPage) || $perPage < 0) {
      $perPage = Settings::productsPerPage();
    }

    switch ($type) {
      case 'vendor':
        $slug = \Drupal::request()->query->get('id');

        if ($slug === NULL || $page === NULL) {
          throw new NotFoundHttpException();
        }

        $vendor = ShopifyVendor::loadBySlug($slug);
        $data = $vendor->renderProductJson($sortOrder, $page, $perPage);
        $tags = [
          'shopify_vendor_products:' . $vendor->id(),
        ];
        break;

      case 'collection':
        $id = \Drupal::request()->query->get('id');

        if ($id === NULL || $page === NULL) {
          throw new NotFoundHttpException();
        }

        $data = ShopifyCollection::renderJson($id, $sortOrder, $page, $perPage);
        $tags = ShopifyCollection::cacheTags($id);
        break;

      case 'product_search':
        $tags = \Drupal::request()->query->get('id');
        $tags = explode('|', $tags);
        $vendors = ShopifyVendor::filterTagsForVendors($tags);

        $data = ShopifyProductSearch::renderJson($sortOrder, $page, $perPage, $tags, $vendors);
        $tags = ['shopify_product_list', 'shopify_vendor_list'];

        break;

      case 'vendors':
        $parts = \Drupal::request()->query->get('id');
        $parts = explode('[]', $parts);

        $filterTags = $parts[0];
        $filterTags = str_replace('tags_', '', $filterTags);
        if (strlen($filterTags) > 0) {
          $filterTags = array_map('trim', explode(',', $filterTags));
        }
        else {
          $filterTags = [];
        }

        $filterTypes = $parts[1];
        $filterTypes = str_replace('types_', '', $filterTypes);
        if (strlen(trim($filterTypes)) > 0) {
          $filterTypes = array_map('trim', explode(',', $filterTypes));
        }
        else {
          $filterTypes = [];
        }

        if ($page === NULL) {
          throw new NotFoundHttpException();
        }

        $params = [
          'tags' => $filterTags,
          'types' => $filterTypes,
        ];

        $data = ShopifyVendors::renderVendorsPageJson($params, $sortOrder, $page, $perPage);
        $tags = ['shopify_product_list', 'shopify_vendors_list'];
    }

    $cache = [
      '#cache' => [
        'contexts' => ['user.roles', 'url.query_args'],
        'tags' => $tags,
      ],
    ];

    $response = new CacheableJsonResponse($data);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($cache));
    return $response;
  }

}
