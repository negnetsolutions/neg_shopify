<?php

namespace Drupal\neg_shopify\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\neg_shopify\Entity\ShopifyProduct;
use Drupal\neg_shopify\Entity\ShopifyProductVariant;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Cache\CacheableRedirectResponse;
use Drupal\Core\Cache\CacheableMetadata;

use Drupal\neg_shopify\Settings;
use Drupal\neg_shopify\ShopifyCollection;

/**
 * Class ShopifyRedirect.
 *
 * Handles redirecting the user.
 */
class ShopifyRedirect extends ControllerBase {

  /**
   * Redirects the incoming user to the proper specific variant or product page.
   */
  public function handleRedirect() {
    $request = \Drupal::request();
    $messenger = \Drupal::messenger();

    if ($request->get('variant_id')) {
      // We are redirecting to a specific variant page.
      $variant = ShopifyProductVariant::loadByVariantId($request->get('variant_id'));
      if ($variant instanceof ShopifyProductVariant) {
        $response = CacheableRedirectResponse::create($variant->getProductUrl());
        $cache = [
          '#cache' => [
            'contexts' => ['user.roles', 'url.query_args'],
            'tags' => $variant->getCacheTags(),
          ],
        ];
        $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($cache));
        return $response;
      }
      $messenger->addWarning(t("We're sorry, but that product is unavailable at this time."));
    }

    if ($request->get('product_id')) {
      // We are redirecting to a product page (no variant selected).
      $product = ShopifyProduct::loadByProductId($request->get('product_id'));
      if ($product instanceof ShopifyProduct) {
        return new RedirectResponse($product->toUrl()->toString());
      }
      $messenger->addWarning(t("We're sorry, but that product is unavailable at this time."));
    }

    if ($request->get('collection_id')) {
      // We are redirecting to a collection page.
      $collection = ShopifyCollection::load($request->get('collection_id'));
      if ($collection instanceof Term) {
        return new RedirectResponse($collection->toUrl()->toString());
      }
      $messenger->addWarning(t("We're sorry, but that collection is unavailable at this time."));
    }

    return new RedirectResponse('/');
  }

  /**
   * Redirects the user to the admin page to add a new product.
   */
  public function addShopifyProduct() {
    return new TrustedRedirectResponse('https://' . Settings::shopInfo('domain') . '/admin/products/new');
  }

}
