<?php

namespace Drupal\neg_shopify;

use Drupal\user\UserInterface;
use Drupal\user\Entity\User;

/**
 * Provides user management for Shopify Users.
 */
class UserManagement {

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
    $users = User::loadMultiple($ids);

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
