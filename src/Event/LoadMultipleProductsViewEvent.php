<?php

namespace Drupal\neg_shopify\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Event that is fired when multiple products have been loaded.
 */
class LoadMultipleProductsViewEvent extends Event {

  const POSTPROCESS = 'neg_shopify_postprocess_loadmultipleproductsview';
  const PREPROCESS = 'neg_shopify_preprocess_loadmultipleproductsview';

  /**
   * The products in the view array.
   *
   * @var array
   */
  public $products;

  /**
   * The view array.
   *
   * @var array
   */
  public $view;

  /**
   * The render style.
   *
   * @var string
   */
  public $style;

  /**
   * Whether we are using default render context.
   *
   * @var bool
   */
  public $defaultContext;

  /**
   * Constructs the object.
   */
  public function __construct($products, &$view, $style, $defaultContext) {
    $this->products = $products;
    $this->view = &$view;
    $this->style = $style;
    $this->defaultContext = $defaultContext;
  }

}
