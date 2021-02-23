<?php

namespace Drupal\neg_shopify;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Shopify vendor entity.
 *
 * @see \Drupal\shopify\Entity\ShopifyVendor.
 */
class ShopifyVendorAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {

    switch ($operation) {
      case 'view':
        return AccessResult::allowedIfHasPermission($account, 'view shopify vendor entities');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit shopify vendor entities');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete shopify vendor entities');
    }

    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add shopify vendor entities');
  }

}
