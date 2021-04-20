<?php

namespace Drupal\neg_shopify\TypedData;

use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\file\Entity\File;

/**
 * Finds dyamic product image for current product and variant.
 */
class DynamicVendorImage extends FieldItemList implements EntityReferenceFieldItemListInterface {

  use ComputedItemListTrait;

  /**
   * Computes the variant image value.
   */
  protected function computeValue() {
    $entity = $this->getEntity();

    $image = NULL;

    if ($entity->thumbnail->target_id) {
      // Check for vendor override image.
      $image = $entity->get('thumbnail')->entity;
    }
    else {
      $products = $entity->getProducts(1);
      if (count($products) > 0) {
        $product = reset($products);
        // Product image.
        if ($product && !$product->get('image')->isEmpty()) {
          $image = $product->get('image')->entity;
        }
      }
    }

    if ($image) {
      $this->list[0] = $this->createItem(0, $image);
    }
  }

  /**
   * Implements referencedEntities.
   */
  public function referencedEntities() {
    $entity = $this->getEntity();

    $tid = NULL;

    if ($entity->thumbnail->target_id) {
      // Check for vendor override image.
      $tid = $entity->thumbnail->target_id;
    }
    else {
      $products = $entity->getProducts(1);
      if (count($products) > 0) {
        $product = reset($products);

        // Product image.
        if ($product && !$product->image->isEmpty()) {
          $tid = $product->image->target_id;
        }
      }
    }

    if ($tid === NULL) {
      return [];
    }

    $file = File::load($tid);
    if (!$file) {
      return [];
    }

    return [$file];
  }

}
