<?php

namespace Drupal\neg_shopify\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;

use Drupal\neg_shopify\ShopifyCollection;
use Drupal\neg_shopify\ShopifyVendors;
use Drupal\neg_shopify\Settings;

/**
 * Class JsonController.
 */
class JsonController extends ControllerBase {

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

    switch ($type) {
      case 'vendor':
        $slug = \Drupal::request()->query->get('id');

        if ($slug === NULL || $page === NULL) {
          throw new NotFoundHttpException();
        }

        $data = ShopifyVendors::renderJson($slug, $sortOrder, $page, $perPage);
        $tags = ['shopify_product_list'];
        break;

      case 'collection':
        $id = \Drupal::request()->query->get('id');

        if ($id === NULL || $page === NULL) {
          throw new NotFoundHttpException();
        }

        $data = ShopifyCollection::renderJson($id, $sortOrder, $page, $perPage);
        $tags = ShopifyCollection::cacheTags($id);
        break;
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
