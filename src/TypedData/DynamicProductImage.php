<?php

namespace Drupal\neg_shopify\TypedData;

use Drupal\neg_shopify\Entity\ShopifyProductVariant;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\file\Entity\File;

/**
 * Finds dyamic product image for current product and variant.
 */
class DynamicProductImage extends FieldItemList implements EntityReferenceFieldItemListInterface {

  use ComputedItemListTrait;

  /**
   * Gets first variant id.
   */
  private function getFirstVariantId(object $variants) {
    foreach ($variants as $variant) {
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

    if (!$entity->get('image')->isEmpty()) {
      $image = $entity->image->first()->entity;
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
      if (!$active_variant->get('image')->isEmpty()) {
        $image = $active_variant->image->first()->entity;
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

    if ($entity->image->target_id) {
      $tid = $entity->image->target_id;
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
        $tid = $active_variant->image->target_id;
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
