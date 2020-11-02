<?php

namespace Drupal\neg_shopify\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\neg_shopify\Settings;

/**
 * Provides the  Block 'CartBlock'.
 *
 * @Block(
 *   id = "neg_shopify_cartblock",
 *   subject = @Translation("Shopify Shopping Cart"),
 *   admin_label = @Translation("Shopify Shopping Cart")
 * )
 */
class CartBlock extends BlockBase {

  /**
   * Implements BlockBase::blockBuild().
   */
  public function build() {
    $build = [
      '#theme' => 'neg_shopify_minicart',
      '#attached' => [
        'library' => [
          'neg_shopify/cart_block',
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
}
