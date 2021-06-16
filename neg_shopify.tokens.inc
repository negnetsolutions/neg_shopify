<?php

/**
 * @file
 * Neg_shopify Tokens.
 */

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\neg_paragraphs\ParagraphHelper;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\image\Entity\ImageStyle;
use Drupal\file\Entity\File;

/**
 * Implements hook_token_info().
 */
function neg_shopify_token_info() {
  return [
    'tokens' => [
      'shopify_product' => [
        'active_variant' => [
          'name' => 'Active Variant',
          'description' => t('Active Variant'),
        ],
        'thumbnail' => [
          'name' => t('Product Image'),
          'description' => t('Product image 800px.'),
        ],
      ],
    ],
  ];
}

/**
 * Implements hook_tokens().
 */
function neg_shopify_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  $replacements = [];

  if ($type == 'shopify_product') {

    foreach ($tokens as $name => $original) {
      $parts = explode(':', $name);

      switch ($parts[0]) {
        case 'thumbnail':
          $images = [];

          if ($product = $data['shopify_product']) {

            if ($product->image->target_id) {
              $path = $product->image->entity->getFileUri();
              $images[] = ImageStyle::load('rs_image_800w')->buildUrl($path);
            }
          }

          if (count($images) > 0) {
            $replacement = implode(',', $images);
            $replacements[$original] = $replacement;
          }
          break;

        case 'active_variant':
          if (count($parts) > 1) {
            $variant = neg_shopify_get_active_product_variant($data[$type]);
            if ($variant) {
              if ($variant->hasField($parts[1]) && !$variant->get($parts[1])->isEmpty()) {
                $replacements[$original] = $variant->get($parts[1])->value;
              }
            }
          }
          break;
      }
    }
  }
  return $replacements;
}
