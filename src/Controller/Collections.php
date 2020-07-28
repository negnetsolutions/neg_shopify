<?php

namespace Drupal\neg_shopify\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\neg_shopify\ShopifyCollection;
use Drupal\neg_shopify\Entity\ShopifyProductSearch;
use Drupal\neg_shopify\Entity\ShopifyProduct;
use Drupal\neg_shopify\Settings;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Render\RenderContext;

/**
 * Class Collections.
 */
class Collections extends ControllerBase {

  /**
   * Renders /collections/xml/handle.
   */
  public function renderFeed($handle) {
    $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['field_handle' => $handle]);

    if (count($term) === 0) {
      throw new NotFoundHttpException('Could not locate collection!');
    }

    $term = reset($term);

    $params = [
      'sort' => Settings::defaultSortOrder(),
    ];

    $show = \Drupal::request()->query->get('show');
    if ($show !== NULL) {
      $params['show'] = $show;
    }

    switch ($term->get('field_type')->value) {
      case 'SmartCollection':
        $params['collection_rules'] = json_decode($term->get('field_rules')->value, TRUE);
        $params['collection_disjunctive'] = (bool) $term->get('field_disjunctive')->value;
        $tags = ShopifyCollection::cacheTags($term->id());
        break;

      default:
        // CustomCollection.
        $params['collection_id'] = $term->id();
        $tags = ShopifyCollection::cacheTags($term->id(), FALSE);
        break;
    }

    $search = new ShopifyProductSearch($params);
    $products = $search->search(0, 1000);

    $build = [
      '#theme' => 'shopify-xml-feed',
      '#products' => ShopifyProduct::loadView($products, 'xml_listing'),
      '#name' => $term->getName(),
    ];

    $cache = [
      '#cache' => [
        'contexts' => ['user.roles', 'url.query_args'],
        'tags' => $tags,
      ],
    ];

    $render = NULL;
    \Drupal::service('renderer')->executeInRenderContext(new RenderContext(), function () use (&$build, &$render, &$term) {
      $build['#link'] = $term->toUrl()->setAbsolute()->toString();
      $render = \Drupal::service('renderer')->render($build);
    });

    $response = new CacheableResponse($render);
    $response->headers->set('Content-type', 'application/xml; charset=utf-8');
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($cache));
    return $response;
  }

  /**
   * Renders /collections/all.
   */
  public function renderAll() {
    $build = [
      '#theme' => 'shopify-collection-all',
      '#name' => 'Products',
    ];
    ShopifyCollection::renderAll($build);
    return $build;
  }

}
