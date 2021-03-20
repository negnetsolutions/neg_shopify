<?php

namespace Drupal\neg_shopify;

use Drupal\user\UserInterface;
use Drupal\user\Entity\User;
use Drupal\neg_shopify\Api\ShopifyService;

/**
 * Provides user management for Shopify Users.
 */
class UserManagement {

  /**
   * Deletes orphaned users.
   */
  public static function deleteOrphanedUsers(array $options = []) {

    $users_ids = [];
    $users = ShopifyService::instance()->fetchAllUsers($options);

    foreach ($users as $user) {
      $gid = 'gid://shopify/Customer/' . $user['id'];
      $users_ids[] = $gid;
    }

    $deleted_users = [];

    $query = \Drupal::entityQuery('user');
    $query->condition('field_shopify_id', $users_ids, 'NOT IN');
    $ids = $query->execute();
    $users = (count($ids) > 0) ? User::loadMultiple($ids) : [];

    foreach ($users as $user) {
      // Check if user can be deleted.
      if (self::verifyUserAllowed($user)) {
        $deleted_users[] = $user;
        $user->delete();
      }
    }

    return $deleted_users;

  }

  /**
   * Syncs user with shopify.
   */
  public static function syncUserWithShopify($shopifyUser, $user = FALSE) {
    $mail = $shopifyUser['email'];

    if (strlen(trim($mail)) == 0) {
      return FALSE;
    }

    $firstName = $shopifyUser['first_name'];
    $lastName = $shopifyUser['last_name'];
    $gid = 'gid://shopify/Customer/' . $shopifyUser['id'];

    // Try to find the user.
    if (!$user) {
      $user = self::loadUserByShopifyId($shopifyUser['id']);
    }

    if (!$user) {
      // Try by mail.
      $user = self::loadUserByMail($mail);
    }

    if (!$user) {
      Settings::log('Creating User: %email', ['%email' => $mail]);
      $user = self::provisionDrupalUser($mail);
    }

    if ($user) {
      Settings::log('Updating User: %email', ['%email' => $mail]);
      $user->field_first_name->setValue(['value' => $firstName]);
      $user->field_last_name->setValue(['value' => $lastName]);
      $user->mail->setValue(['value' => $mail]);
      $user->field_shopify_id->setValue(['value' => $gid]);
      $user->save();

      return TRUE;
    }

    return FALSE;
  }

  /**
   * Tries to load load a drupal user by shopify id.
   */
  public static function loadUserByShopifyId($id) {

    if (!str_starts_with($id, 'gid://shopify/Customer/')) {
      $id = "gid://shopify/Customer/$id";
    }

    $query = \Drupal::entityQuery('user');
    $query->condition('field_shopify_id', $id, '=');
    $ids = $query->execute();
    $users = (count($ids) > 0) ? User::loadMultiple($ids) : [];

    return (count($users) > 0) ? reset($users) : NULL;
  }

  /**
   * Tries to load load a drupal user by email.
   */
  public static function loadUserByMail($mail) {

    $load_by_name = \Drupal::entityTypeManager()->getStorage('user')
      ->loadByProperties(['name' => $mail]);
    return $load_by_name ? reset($load_by_name) : NULL;
  }

  /**
   * Get's admin roles.
   */
  public static function getAdminRoles() {
    return \Drupal::entityTypeManager()
      ->getStorage('user_role')
      ->getQuery()
      ->condition('is_admin', TRUE)
      ->execute();
  }

  /**
   * Verifies whether the user is available or can be created.
   *
   * @return bool
   *   Whether to allow user login.
   *
   * @todo This duplicates DrupalUserProcessor->excludeUser().
   */
  public static function verifyUserAllowed($user): bool {
    // Dissalow Administrators.
    $admin_roles = self::getAdminRoles();
    if (!empty(array_intersect($user->getRoles(), $admin_roles))) {
      return FALSE;
    }

    // Make sure user is a shopify customer.
    if (!$user->hasRole('shopify_customer')) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Verifies whether the user is available or can be created.
   *
   * @return bool
   *   Whether to allow user login and creation.
   */
  public static function verifyAccountCreation(): bool {

    // Only allow if user registration is open.
    if (\Drupal::config('user.settings')->get('register') === UserInterface::REGISTER_VISITORS) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Provision the Drupal user.
   *
   * @return bool
   *   Provisioning successful.
   */
  public static function provisionDrupalUser($mail): object {
    $users = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->loadByProperties(['mail' => $mail]);

    $user_data = [
      'name' => $mail,
      'mail' => $mail,
      'status' => 1,
    ];

    $account = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->create($user_data);
    $account->enforceIsNew();
    $account->addRole('shopify_customer');
    $account->save();

    return $account;
  }

  /**
   * Get's shopifyUserAccessToken.
   */
  public static function getAccessToken($uid = FALSE) {
    if ($uid === FALSE) {
      $current_user = \Drupal::currentUser();
      $uid = $current_user->id();
    }

    return \Drupal::state()->get("shopify_user_access_token_$uid");
  }

  /**
   * Clears state for user.
   */
  public static function clearShopifyUserDetailsState($user) {
    $uid = $user->id();
    \Drupal::state()->delete("shopify_user_details_$uid");
  }

  /**
   * Clears state for user.
   */
  public static function clearShopifyUserState($user) {
    $uid = $user->id();
    \Drupal::state()->delete("shopify_user_access_token_$uid");
  }

  /**
   * Sets shopify user state data.
   */
  public static function setShopifyUserState($user, $data) {
    // Login the user and set shopify state variables.
    $state = [
      'shopify_user_access_token_' . $user->id() => $data['accessToken'],
    ];
    \Drupal::state()->setMultiple($state);

    return TRUE;
  }

}
