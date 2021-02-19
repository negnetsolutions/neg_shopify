<?php

namespace Drupal\neg_shopify\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\neg_shopify\Entity\ShopifyProduct;
use Drupal\neg_shopify\ShopifyCollection;
use Drupal\neg_shopify\Settings;
use Drupal\neg_shopify\UserManagement;
use Drupal\neg_shopify\ShopifyCustomer;

/**
 * Class ShopifyWebhook.
 */
class ShopifyWebhook extends QueueWorkerBase {

  /**
   * Processes a queue item.
   */
  public function processItem($data) {

    switch ($data['hook']) {
      case 'customers/create':
      case 'customers/update':
        $allowShopifyLogins = (BOOL) Settings::config()->get('allow_shopify_users');
        if (!$allowShopifyLogins) {
          break;
        }

        $mail = $data['payload']['email'];
        $firstName = $data['payload']['first_name'];
        $lastName = $data['payload']['last_name'];
        $gid = 'gid://shopify/Customer/' . $data['payload']['id'];

        // Try to find the user.
        $user = UserManagement::loadUserByShopifyId($data['payload']['id']);

        // Check for email address change.
        if ($user && $user->getEmail() != $mail) {
          Settings::log('User email change from %email1 to %email2. Deleting Original User.', ['%email1' => $user->getEmail(), '%email2' => $mail]);
          // Let's delete this user and add a new one.
          UserManagement::clearShopifyUserState($user);
          $user->delete();
          $user = NULL;
        }

        if (!$user) {
          Settings::log('Creating User: %email', ['%email' => $mail]);
          $user = UserManagement::provisionDrupalUser($mail);
        }

        if ($user) {
          Settings::log('Updating User: %email', ['%email' => $mail]);
          $user->field_first_name->setValue(['value' => $firstName]);
          $user->field_last_name->setValue(['value' => $lastName]);
          $user->field_shopify_id->setValue(['value' => $gid]);
          $user->save();
        }

        break;

      case 'customers/delete':
        $gid = 'gid://shopify/Customer/' . $data['payload']['id'];
        $user = UserManagement::loadUserByShopifyId($gid);

        if ($user) {
          // Check if user is an admin.
          $admin_roles = UserManagement::getAdminRoles();
          if (!empty(array_intersect($user->getRoles(), $admin_roles))) {
            // If so; don't delete.
            return;
          }

          Settings::log('Deleting User: %email', ['%email' => $user->getEmail()]);

          // Delete the user.
          UserManagement::clearShopifyUserState($user);
          $user->delete();
        }
        break;

      case 'products/create':
      case 'products/update':
        $product = ShopifyProduct::updateProduct($data['payload']);
        if ($product !== FALSE) {
          Settings::log('Synced Product id: %id', ['%id' => $data['payload']['id'], 'debug']);
        }
        else {
          throw new \Exception('Could not update product id: ' . $data['payload']['id']);
        }
        break;

      case 'products/delete':
        $product = ShopifyProduct::loadByProductId($data['payload']['id']);
        if ($product !== FALSE) {
          $product->delete();
          Settings::log('Deleted Product id: %id', ['%id' => $data['payload']['id'], 'debug']);
        }
        break;

      case 'collections/create':
      case 'collections/update':
        $collection = $data['payload'];
        $term = ShopifyCollection::load($collection['id']);
        if (!$term) {
          // Need to create a new collection.
          ShopifyCollection::create($collection, TRUE);
        }
        else {
          ShopifyCollection::update($collection, TRUE);
        }
        Settings::log('Synced %id Collection', ['%id' => $collection['title'], 'debug']);
        break;

      case 'collections/delete':
        $collection = $data['payload'];
        $term = ShopifyCollection::load($collection['id']);
        if ($term !== FALSE) {
          $term->delete();
          Settings::log('Deleted %id Collection', ['%id' => $collection['id'], 'debug']);
        }
        break;
    }
  }

}
