<?php

namespace Drupal\neg_shopify\Entity\EntityTrait;

use Drupal\Core\File\FileSystemInterface;

/**
 * Class ShopifyEntityTrait.
 */
trait ShopifyEntityTrait {

  /**
   * Format date as timestamp.
   */
  public static function formatDatetimeAsTimestamp(array $fields, array &$values = []) {
    foreach ($fields as $field) {
      if (isset($values[$field]) && !is_int($values[$field])) {
        $values[$field] = strtotime($values[$field]);
      }
    }
  }

  /**
   * Sets up product image.
   */
  public static function setupProductImage($image_url) {
    $directory = file_build_uri('shopify_images');
    if (!\Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY)) {
      // If our directory doesn't exist and can't be created, use the default.
      $directory = NULL;
    }

    $file = self::retrieveFile($image_url, $directory);
    return $file;
  }

  /**
   * Retrieves a shopify image file.
   */
  protected static function retrieveFile($url, $destination) {

    $parsed_url = parse_url($url);
    $fs = \Drupal::service('file_system');

    if (is_dir($fs->realpath($destination))) {

      // Inject shopify version into filename.
      $basename = $fs->basename($parsed_url['path']);
      $filename_parts = pathinfo($basename);
      $version = (isset($parsed_url['query'])) ? hash('crc32', $parsed_url['query']) : '00000000';
      $filename = $filename_parts['filename'] . '_' . $version . '.' . $filename_parts['extension'];

      // Prevent URIs with triple slashes when glueing parts together.
      $path = str_replace('///', '//', "{$destination}/") . $filename;
    }
    else {
      $path = $destination;
    }

    // Check to see if file is already downloaded.
    $existing_files = \Drupal::entityTypeManager()->getStorage('file')->loadByProperties([
      'uri' => $path,
    ]);

    // If it exists, return that file.
    if (count($existing_files) > 0) {
      $existing = reset($existing_files);
      return $existing;
    }

    try {
      $data = (string) \Drupal::httpClient()
        ->get($url)
        ->getBody();
      $local = file_save_data($data, $path, FileSystemInterface::EXISTS_REPLACE);
    }
    catch (RequestException $exception) {
      \Drupal::logger('neg_shopify')->error(t('Failed to fetch file due to error "%error"', [
        '%error' => $exception
          ->getMessage(),
      ]));
      return FALSE;
    }

    return $local;
  }

}
