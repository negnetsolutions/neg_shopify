<?php

namespace Drupal\neg_shopify\Entity\AccessControlHandler;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Shopify product entity.
 *
 * @see \Drupal\shopify\Entity\ShopifyProduct.
 */
class ShopifyProductAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {

    switch ($operation) {
      case 'view':
        $vendor = $entity->getShopifyVendor();

        $published = TRUE;

        if ($vendor) {
          if (!$vendor->get('status')->value) {
            $published = FALSE;
          }
        }

        // Handle unpublished items.
        if ($entity->get('published_at')->value === NULL) {
          $published = FALSE;
        }

        // Handle draft / archived.
        if ($entity->get('status')->value == FALSE) {
          $published = FALSE;
        }

        if ($published) {
          return AccessResult::allowedIfHasPermission($account, 'view shopify product entities');
        }
        else {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished shopify vendor entities');
        }

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit shopify product entities');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete shopify product entities');
    }

    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add shopify product entities');
  }

}
