<?php

namespace Drupal\neg_shopify\TwigExtension;

use Twig_Extension;
use Twig_SimpleFilter;

/**
 * Class ShopifyFormatters.
 */
class ShopifyFormatters extends Twig_Extension {

  /**
   * {@inheritdoc}
   * This function must return the name of the extension. It must be unique.
   */
  public function getName() {
    return 'shopify_twig_formatters.twig_extension';
  }

  /**
   * In this function we can declare the extension function.
   */
  public function getFilters() {
    return [
      new Twig_SimpleFilter('shopify_date', [$this, 'formatDate']),
      new Twig_SimpleFilter('shopify_amount', [$this, 'formatAmount']),
      new Twig_SimpleFilter('shopify_currency', [$this, 'formatCurrency']),
      new Twig_SimpleFilter('display_shopify_financial_status', [$this, 'formatFinancialStatus']),
    ];
  }

  /**
   * Formats financial status.
   */
  public function formatFinancialStatus($object) {

    $object = str_replace('_', ' ', $object);
    $object = strtolower($object);
    return ucwords($object);
  }

  /**
   * Formats a shopify currency object.
   */
  public function formatCurrency($object) {
    $currency = 'USD';

    if (isset($object['currencyCode'])) {
      $currency = strtoupper($object['currencyCode']);
    }

    if (isset($object['amount'])) {
      $output = '';

      if ($currency === 'USD') {
        $output .= '$';
      }

      $output .= $this->formatAmount($object['amount']);

      if ($currency !== 'USD') {
        $output .= " $currency";
      }

      return $output;
    }

    return $object;
  }

  /**
   * Formats dates for Shopify.
   */
  public function formatDate($item) {
    $dt = new \DateTime($item);
    if ($dt) {
      $item = $dt->format('m-d-Y');
    }

    return $item;
  }

  /**
   * Formats amounts.
   */
  public function formatAmount($item) {
    if (is_numeric($item)) {
      $item = number_format($item, 2);
    }
    return $item;
  }
}
