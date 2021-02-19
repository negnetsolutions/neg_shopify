<?php

namespace Drupal\neg_shopify\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\neg_shopify\Settings;

/**
 * Provides the  Block 'OrderHistory'.
 *
 * @Block(
 *   id = "neg_shopify_order_history",
 *   subject = @Translation("Shopify Customer Order History"),
 *   admin_label = @Translation("Shopify Customer Order History")
 * )
 */
class OrderHistoryBlock extends BlockBase {

  /**
   * Implements BlockBase::blockBuild().
   */
  public function build() {
    $build = [
      '#theme' => 'neg_shopify_user_order_history',
      '#attached' => [
        'library' => [
          'neg_shopify/user_order_history_block',
        ],
      ],
      '#cache' => [
        'content' => ['url'],
        // 'tags' => ['neg_shopify_ord'],
      ],
    ];

    Settings::attachShopifyJs($build);

    return $build;
  }
}
