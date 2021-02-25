<?php

namespace Drupal\neg_shopify\Plugin\ExtraField\Display;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\extra_field_plus\Plugin\ExtraFieldPlusDisplayFormattedBase;
use Drupal\responsive_image\Entity\ReponsiveImageStyle;
use Drupal\neg_shopify\Entity\ShopifyProductSearch;
use Drupal\neg_shopify\Settings;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "thumbnail",
 *   label = @Translation("Vendor Thumbnail"),
 *   bundles = {
 *     "shopify_vendor.*",
 *   }
 * )
 */
class ThumbnailFormattedField extends ExtraFieldPlusDisplayFormattedBase {

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->t('Vendor Thumbnail');
  }

  /**
   * {@inheritdoc}
   */
  public function getLabelDisplay() {
    return 'none';
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(ContentEntityInterface $entity) {
    $settings = $this->getSettings();

    $params = [
      'sort' => Settings::defaultSortOrder(),
      'vendor_slug' => $entity->get('slug')->value,
    ];

    $search = new ShopifyProductSearch($params);
    $products = $search->search(0, 1);

    $build = [];

    if (count($products) > 0) {
      $product = reset($products);
      if ($product->image->target_id) {
        $view = $product->image->view();
        if (count($view) > 0 && isset($view[0])) {
          $build = $view[0];
          $build['#theme'] = 'responsive_image_formatter';
          $build['#responsive_image_style_id'] = $settings['responsive_image_style'];
        }
      }
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm() {
    $form = parent::settingsForm();

    $styles = \Drupal::entityTypeManager()
      ->getStorage('responsive_image_style')
      ->getQuery()
      ->execute();

    $form['responsive_image_style'] = [
      '#type' => 'select',
      '#title' => $this->t('Image Style'),
      '#options' => $styles,
    ];

    return $form;
  }

  /**
   *    * {@inheritdoc}
   *       */
  public function defaultFormValues() {
    $values = parent::defaultFormValues();

    $values += [
      'responsive_image_style' => 'rs_image',
    ];

    return $values;
  }
}
