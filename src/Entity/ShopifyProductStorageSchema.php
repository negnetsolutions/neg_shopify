<?php

namespace Drupal\neg_shopify\Entity;

use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;
use Drupal\Core\Entity\ContentEntityTypeInterface;

/**
 * Defines the node schema handler.
 */
class ShopifyProductStorageSchema extends SqlContentEntityStorageSchema {

  /**
   * {@inheritdoc}
   */
  protected function getEntitySchema(ContentEntityTypeInterface $entity_type, $reset = FALSE) {
    $schema = parent::getEntitySchema($entity_type, $reset);

    \Drupal::logger('neg_shopify')->error('HERE', []);

    if ($data_table = $this->storage->getDataTable()) {
      $schema[$data_table]['indexes'] += [
        'shopify_product__avaiable' => ['is_available'],
        'shopify_product__vendor_slug' => ['vendor_slug'],
        'shopify_product__low_price' => ['low_price'],
      ];
    }

    return $schema;
  }

}
