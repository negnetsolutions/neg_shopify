<?php

namespace Drupal\neg_shopify\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * @Constraint(
 * id = "EmptyDeletedProducts",
 * label = @Translation("Remove deleted shopify products.", context="Validation")
 * )
 */
class EmptyDeletedProducts extends Constraint {
  public $nonExistingMessage = 'The donation amount must be below %type';
}
