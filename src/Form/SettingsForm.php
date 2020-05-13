<?php

namespace Drupal\neg_shopify\Form;

use Drupal\neg_shopify\Settings;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

use Drupal\neg_shopify\Plugin\Sync;
use Drupal\neg_shopify\Plugin\Webhooks;

/**
 * Settings for Shopify.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'shopify_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      Settings::CONFIGNAME,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(Settings::CONFIGNAME);

    $form['shop_url'] = [
      '#type' => 'textfield',
      '#title' => t('Shopify Shop Name'),
      '#default_value' => $config->get('shop_url'),
      '#description' => t('Enter your Shopify Shop Name [name].myshopify.com'),
      '#required' => TRUE,
    ];

    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => t('Shopify API Key'),
      '#default_value' => $config->get('api_key'),
      '#description' => t('Enter your Shopify API Key'),
      '#required' => TRUE,
    ];

    $form['api_password'] = [
      '#type' => 'textfield',
      '#title' => t('Shopify API Password'),
      '#default_value' => $config->get('api_password'),
      '#description' => t('Enter your Shopify API Password'),
      '#required' => TRUE,
    ];

    $form['api_secret'] = [
      '#type' => 'textfield',
      '#title' => t('Shopify Shared Secret'),
      '#default_value' => $config->get('api_secret'),
      '#description' => t('Enter your Shopify Shared Secret'),
      '#required' => TRUE,
    ];

    // $form['frequency'] = [
    //   '#type' => 'select',
    //   '#title' => t('Sync Frequency'),
    //   '#default_value' => $config->get('frequency'),
    //   '#options' => [
    //     '0' => 'Every Cron Run',
    //     '21600' => 'Every 12 Hours',
    //     '86400' => 'Every 24 Hours',
    //   ],
    //   '#required' => TRUE,
    // ];

    if ($config->get('api_key') !== NULL) {
      $form['webhooks'] = [
        '#type' => 'details',
        '#title' => t('Webhooks'),
      ];

      $hooks = Webhooks::getWebhooksData();
      $form['webhooks']['table'] = [
        '#type' => 'table',
        '#header' => Webhooks::getWebhooksDataTableHeaders(),
        '#rows' => $hooks,
        '#empty' => t('No Webhooks Installed'),
      ];

      if (count($hooks) > 0) {
        $form['webhooks']['remove_hooks'] = [
          '#type' => 'submit',
          '#value' => t('Uninstall All Hooks'),
          '#submit' => ['::uninstallHooks'],
        ];
      }

      $form['webhooks']['install_hooks'] = [
        '#type' => 'submit',
        '#value' => t('Install Hooks'),
        '#submit' => ['::installHooks'],
      ];

      $products_last_sync_time_formatted = date('r', $config->get('last_product_sync'));
      $collections_last_sync_time_formatted = date('r', $config->get('last_collection_sync'));

      $form['products'] = [
        '#type' => 'details',
        '#title' => t('Sync Products'),
        '#description' => t('Last sync time: @time', [
          '@time' => $products_last_sync_time_formatted,
        ]),
      ];

      $form['products']['reset_products'] = [
        '#type' => 'submit',
        '#value' => t('Reset Products Last Sync Time and Queue Sync'),
        '#submit' => ['::resetProductsSync'],
      ];

      $form['products']['sync_products'] = [
        '#type' => 'submit',
        '#value' => t('Queue Product Sync Now'),
        '#submit' => ['::forceProductSync'],
      ];

      $form['collections'] = [
        '#type' => 'details',
        '#title' => t('Sync Collections'),
        '#description' => t('Last sync time: @time', [
          '@time' => $collections_last_sync_time_formatted,
        ]),
      ];

      $form['collections']['reset_collections'] = [
        '#type' => 'submit',
        '#value' => t('Reset Collections Last Sync Time and Queue Sync'),
        '#submit' => ['::resetCollectionSync'],
      ];

      $form['collections']['sync_collections'] = [
        '#type' => 'submit',
        '#value' => t('Queue Collection Sync Now'),
        '#submit' => ['::forceCollectionsSync'],
      ];
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * Removes all hooks.
   */
  public function uninstallHooks(array &$form, FormStateInterface $form_state) {
    Webhooks::uninstallWebhooks();
  }

  /**
   * Installs all Hooks.
   */
  public function installHooks(array &$form, FormStateInterface $form_state) {
    Webhooks::installWebhooks();
  }

  /**
   * Forces a collections resync.
   */
  public function resetCollectionSync(array &$form, FormStateInterface $form_state) {
    $config = Settings::editableConfig();
    $config->clear('last_collection_sync');
    $config->save();
    Sync::syncAllCollections();
  }

  /**
   * Forces a collections resync.
   */
  public function forceCollectionsSync(array &$form, FormStateInterface $form_state) {
    Sync::syncAllCollections();
  }

  /**
   * Forces a resync.
   */
  public function resetProductsSync(array &$form, FormStateInterface $form_state) {
    $config = Settings::editableConfig();
    $config->clear('last_product_sync');
    $config->save();
    Sync::syncAllProducts();
  }

  /**
   * Forces a resync.
   */
  public function forceProductSync(array &$form, FormStateInterface $form_state) {
    Sync::syncAllProducts();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Retrieve the configuration.
    $config = Settings::editableConfig();

    $config->set('shop_url', $form_state->getValue('shop_url'))
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('api_secret', $form_state->getValue('api_secret'))
      ->set('api_password', $form_state->getValue('api_password'));

    $config->save();

    parent::submitForm($form, $form_state);
  }

}