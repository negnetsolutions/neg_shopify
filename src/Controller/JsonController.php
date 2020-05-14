<?php

namespace Drupal\neg_shopify\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;

use Drupal\neg_shopify\ShopifyCollection;

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

    switch ($type) {
      case 'collection':
        $id = \Drupal::request()->query->get('id');
        $page = \Drupal::request()->query->get('page');
        $perPage = \Drupal::request()->query->get('perpage');
        $perPage = ($perPage === NULL) ? ShopifyCollection::PERPAGE : $perPage;

        if ($id === NULL || $page === NULL) {
          throw new NotFoundHttpException();
        }

        $data = ShopifyCollection::renderJson($id, $page, $perPage);
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
