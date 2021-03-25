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

    if ($entity->thumbnail->target_id) {
      // Check for vendor override image.
      $image = $entity->get('thumbnail')->getValue()[0];
    }
    else {
      $products = $entity->getProducts(1);
      if (count($products) > 0) {
        $product = reset($products);
        // Product image.
        $image = $product->get('image')->getValue()[0];
      }
    }

    $this->list[0] = $this->createItem(0, $image);
  }

  /**
   * Implements referencedEntities.
   */
  public function referencedEntities() {
    $entity = $this->getEntity();

    $image = FALSE;

    if ($entity->thumbnail->target_id) {
      // Check for vendor override image.
      $image = $entity->get('thumbnail')->getValue()[0];
    }
    else {
      $products = $entity->getProducts(1);
      if (count($products) > 0) {
        $product = reset($products);

        // Product image.
        $image = $product->get('image')->getValue()[0];
      }
    }

    if (!$image) {
      return [];
    }

    $file = File::load($image['target_id']);

    return [$file];
  }

}
