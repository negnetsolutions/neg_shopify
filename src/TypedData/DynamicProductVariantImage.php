<?php

namespace Drupal\neg_shopify\TypedData;

use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;

/**
 * Finds dyamic product image for current product and variant.
 */
class DynamicProductVariantImage extends FieldItemList implements EntityReferenceFieldItemListInterface {

  use ComputedItemListTrait;

  /**
   * Computes the variant image value.
   */
  protected function computeValue() {
    $entity = $this->getEntity();
    $product = $entity->getProduct();

    $image = NULL;

    // Variant's image.
    if (!$entity->get('image')->isEmpty()) {
      $image = $entity->get('image')->getValue()[0];
    }
    elseif (!$product->get('image')->isEmpty()) {
      // Product image.
      $image = $product->get('image')->getValue()[0];
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
    $product = $entity->getProduct();

    // Variant's image.
    if ($entity->image->target_id) {
      $image = $entity->get('image')->getValue()[0];
    }
    else {
      // Product image.
      $image = $product->get('image')->getValue()[0];
    }

    return [$image['target_id']];
  }

}
