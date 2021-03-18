<?php

namespace Drupal\neg_shopify\Api;

/**
 * Custom GraphQL Exception.
 */
class GraphQlException extends \Exception {

  /**
   * {@inheritdoc}
   */
  protected $errors = [];

  /**
   * {@inheritdoc}
   */
  protected $graphQl = '';

  /**
   * Implements __construct().
   */
  public function __construct($errors, $graphQl) {
    $this->errors = $errors;
    $this->graphQl = $graphQl;
  }

  /**
   * {@inheritdoc}
   */
  public function getErrors() {
    return $this->errors;
  }

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    return print_r($this->errors, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getGraphQl() {
    return $this->graphQl;
  }

}
