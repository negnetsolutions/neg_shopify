<?php

namespace Drupal\neg_shopify\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Event that is fired when a user logs in.
 */
class UserDataAuthorizeEvent extends Event {

  const USERORDERSACCESS = 'neg_shopify_alter_user_orders_access';

  /**
   * The account interface object.
   *
   * @var array
   */
  public $account;

  /**
   * The access results.
   *
   * @var array
   */
  public $result;

  /**
   * Constructs the object.
   */
  public function __construct($account, &$result) {
    $this->result = &$result;
    $this->account = $account;
  }

}
