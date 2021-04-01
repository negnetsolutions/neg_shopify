<?php

namespace Drupal\neg_shopify\TypedData;

use Drupal\neg_shopify\Entity\ShopifyProductVariant;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;

/**
 * Finds dyamic product image for current product and variant.
 */
class DynamicProductImage extends FieldItemList implements EntityReferenceFieldItemListInterface {

  use ComputedItemListTrait;

  /**
   * Gets first variant id.
   */
  private function getFirstVariantId(object $variants) {
    foreach ($variants as $i => $variant) {
      if ($variant->entity->isAvailable()) {
        return $variant->entity->id();
      }
    }

    return FALSE;
  }

  /**
   * Computes the variant image value.
   */
  protected function computeValue() {
    $entity = $this->getEntity();

    $image = NULL;

    $imageValue = $entity->get('image')->getValue();
    if (is_array($imageValue) && count($imageValue) > 0) {
      $image = $imageValue[0];
    }

    if ($variant_id = \Drupal::request()->get('variant_id')) {
      $active_variant = ShopifyProductVariant::loadByVariantId($variant_id);
    }
    else {
      $variants = $entity->variants;
      $variant_id = $this->getFirstVariantId($variants);
      $active_variant = ShopifyProductVariant::load($variant_id);
    }

    if ($active_variant instanceof ShopifyProductVariant) {
      if ($active_variant->image->target_id) {
        $image = $active_variant->image->getValue()[0];
      }
    }

    $this->list[0] = $this->createItem(0, $image);
  }

  /**
   * Implements referencedEntities.
   */
  public function referencedEntities() {
    $entity = $this->getEntity();

    $image = $entity->get('image')->value[0];

    if ($variant_id = \Drupal::request()->get('variant_id')) {
      $active_variant = ShopifyProductVariant::loadByVariantId($variant_id);
    }
    else {
      $variants = $entity->variants;
      $variant_id = $this->getFirstVariantId($variants);
      $active_variant = ShopifyProductVariant::load($variant_id);
    }

    if ($active_variant instanceof ShopifyProductVariant) {
      if ($active_variant->image->target_id) {
        $image = $active_variant->image->value[0];
      }
    }

    return [$image['target_id']];
  }

}
