<?php

namespace Drupal\neg_shopify\Form;

use Drupal\neg_shopify\Settings;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\Role;

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

    $form['api'] = [
      '#type' => 'details',
      '#title' => t('Api Connection'),
    ];

    $form['api']['shop_url'] = [
      '#type' => 'textfield',
      '#title' => t('Shopify Shop Name'),
      '#default_value' => $config->get('shop_url'),
      '#description' => t('Enter your Shopify Shop Name [name].myshopify.com'),
      '#required' => TRUE,
    ];

    $form['api']['api_key'] = [
      '#type' => 'textfield',
      '#title' => t('Shopify API Key'),
      '#default_value' => $config->get('api_key'),
      '#description' => t('Enter your Shopify API Key'),
      '#required' => TRUE,
    ];

    $form['api']['api_password'] = [
      '#type' => 'textfield',
      '#title' => t('Shopify API Password'),
      '#default_value' => $config->get('api_password'),
      '#description' => t('Enter your Shopify API Password'),
      '#required' => TRUE,
    ];

    $form['api']['api_secret'] = [
      '#type' => 'textfield',
      '#title' => t('Shopify Shared Secret'),
      '#default_value' => $config->get('api_secret'),
      '#description' => t('Enter your Shopify Shared Secret'),
      '#required' => TRUE,
    ];

    $form['api']['store_front_access_token'] = [
      '#type' => 'textfield',
      '#title' => t('Shopify Storefront Access Token'),
      '#default_value' => $config->get('store_front_access_token'),
      '#description' => t('Enter your Shopify Storefront Access Token'),
      '#required' => TRUE,
    ];

    $form['api']['reset_shop_info'] = [
      '#type' => 'submit',
      '#value' => t('Reload Shop Info Cache'),
      '#submit' => ['::reloadShopInfoCache'],
    ];

    $form['user_management'] = [
      '#type' => 'details',
      '#title' => t('User Management'),
    ];

    $form['user_management']['allow_shopify_users'] = [
      '#type' => 'checkbox',
      '#title' => t('Allow users to login with their Shopify Accounts.'),
      '#default_value' => $config->get('allow_shopify_users'),
    ];

    $form['product_display'] = [
      '#type' => 'details',
      '#title' => t('Product Display'),
    ];

    $form['product_display']['google_product_category'] = [
      '#type' => 'textfield',
      '#title' => t('Default Google Product Category'),
      '#default_value' => $config->get('google_product_category'),
      '#required' => TRUE,
    ];

    $form['product_display']['products_per_page'] = [
      '#type' => 'number',
      '#title' => t('Products to display per page'),
      '#default_value' => ($config->get('products_per_page') !== NULL) ? $config->get('products_per_page') : 5,
      '#description' => t('Enter the number of products to display on each page.'),
      '#required' => TRUE,
    ];

    $form['product_display']['products_label'] = [
      '#type' => 'textfield',
      '#title' => t('Product Label'),
      '#default_value' => ($config->get('products_label') !== NULL) ? $config->get('products_label') : 'products',
      '#maxlength' => '255',
      '#description' => t('Enter product label, ie., products/items.'),
      '#required' => TRUE,
    ];

    $form['product_display']['presale_text'] = [
      '#type' => 'textfield',
      '#title' => t('Presale Text'),
      '#default_value' => ($config->get('presale_text') !== NULL) ? $config->get('presale_text') : '<p>Available for preorder.</p>',
      '#maxlength' => '255',
      '#description' => t('Enter html to display for presale items.'),
      '#required' => TRUE,
    ];

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
        $form['webhooks']['remove_relevant_hooks'] = [
          '#type' => 'submit',
          '#value' => t('Uninstall Relevant Hooks'),
          '#submit' => ['::uninstallRelevantHooks'],
        ];
      }

      $form['webhooks']['install_hooks'] = [
        '#type' => 'submit',
        '#value' => t('Install Hooks'),
        '#submit' => ['::installHooks'],
      ];

      $products_last_sync_time_formatted = date('r', \Drupal::state()->get('neg_shopify.last_product_sync', 0));
      $collections_last_sync_time_formatted = date('r', \Drupal::state()->get('neg_shopify.last_collection_sync', 0));

      $form['products'] = [
        '#type' => 'details',
        '#title' => t('Sync Products'),
        '#description' => t('Last sync time: @time', [
          '@time' => $products_last_sync_time_formatted,
        ]),
      ];

      $form['products']['products_frequency'] = [
        '#type' => 'select',
        '#title' => t('Full Sync Frequency'),
        '#default_value' => $config->get('products_frequency'),
        '#options' => [
          '0' => 'Never',
          '21600' => 'Every 6 Hours',
          '43200' => 'Every 12 Hours',
          '86400' => 'Every 24 Hours',
        ],
        '#required' => TRUE,
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

      $form['collections']['collections_frequency'] = [
        '#type' => 'select',
        '#title' => t('Full Sync Frequency'),
        '#default_value' => $config->get('collections_frequency'),
        '#options' => [
          '0' => 'Never',
          '21600' => 'Every 6 Hours',
          '43200' => 'Every 12 Hours',
          '86400' => 'Every 24 Hours',
        ],
        '#required' => TRUE,
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

      $form['users'] = [
        '#type' => 'details',
        '#title' => t('Sync Users'),
        '#description' => t('Last sync time: @time', [
          '@time' => $collections_last_sync_time_formatted,
        ]),
      ];

      $form['users']['users_frequency'] = [
        '#type' => 'select',
        '#title' => t('Full Sync Frequency'),
        '#default_value' => $config->get('users_frequency'),
        '#options' => [
          '0' => 'Never',
          '21600' => 'Every 6 Hours',
          '43200' => 'Every 12 Hours',
          '86400' => 'Every 24 Hours',
        ],
        '#required' => TRUE,
      ];

      $form['users']['reset_users'] = [
        '#type' => 'submit',
        '#value' => t('Reset Users Last Sync Time and Queue Sync'),
        '#submit' => ['::resetUsersSync'],
      ];

      $form['users']['sync_users'] = [
        '#type' => 'submit',
        '#value' => t('Queue users Sync Now'),
        '#submit' => ['::forceUsersSync'],
      ];

      $form['delete'] = [
        '#type' => 'details',
        '#title' => t('Data Reset'),
      ];

      $form['delete']['delete_products'] = [
        '#type' => 'submit',
        '#value' => t('Delete All Products'),
        '#submit' => ['::deleteAllProducts'],
      ];

      $form['delete']['delete_vendors'] = [
        '#type' => 'submit',
        '#value' => t('Delete All Vendors'),
        '#submit' => ['::deleteAllVendors'],
      ];

      $form['delete']['delete_collections'] = [
        '#type' => 'submit',
        '#value' => t('Delete All Collections'),
        '#submit' => ['::deleteAllCollections'],
      ];

      $form['delete']['delete_tags'] = [
        '#type' => 'submit',
        '#value' => t('Delete All Tags'),
        '#submit' => ['::deleteAllTags'],
      ];

      $form['delete']['delete_shopify_users'] = [
        '#type' => 'submit',
        '#value' => t('Delete All Shopify Customers'),
        '#submit' => ['::deleteAllCustomers'],
      ];
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * Deletes all customers.
   */
  public function deleteAllCustomers(array &$form, FormStateInterface $form_state) {
    Sync::deleteAllCustomers();
  }

  /**
   * Deletes all vendors.
   */
  public function deleteAllVendors(array &$form, FormStateInterface $form_state) {
    Sync::deleteAllVendors();
  }

  /**
   * Deletes all products.
   */
  public function deleteAllProducts(array &$form, FormStateInterface $form_state) {
    Sync::deleteAllProducts();
  }

  /**
   * Deletes all collections.
   */
  public function deleteAllCollections(array &$form, FormStateInterface $form_state) {
    Sync::deleteAllCollections();
  }

  /**
   * Deletes all tags.
   */
  public function deleteAllTags(array &$form, FormStateInterface $form_state) {
    Sync::deleteAllTags();
  }

  /**
   * Removes all hooks.
   */
  public function uninstallRelevantHooks(array &$form, FormStateInterface $form_state) {
    Webhooks::uninstallRelevantWebhooks();
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
  public function resetUsersSync(array &$form, FormStateInterface $form_state) {
    \Drupal::state()->set('neg_shopify.last_users_sync', 0);
    Sync::syncAllUsers();
  }

  /**
   * Forces a collections resync.
   */
  public function forceUsersSync(array &$form, FormStateInterface $form_state) {
    Sync::syncAllUsers();
  }

  /**
   * Forces a collections resync.
   */
  public function resetCollectionSync(array &$form, FormStateInterface $form_state) {
    \Drupal::state()->set('neg_shopify.last_collection_sync', 0);
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
    \Drupal::state()->set('neg_shopify.last_product_sync', 0);
    Sync::syncAllProducts();
  }

  /**
   * Forces a resync.
   */
  public function forceProductSync(array &$form, FormStateInterface $form_state) {
    Sync::syncAllProducts();
  }

  /**
   * Checks that user roles are in place.
   */
  protected function checkUserRoles() {
    $roles = \Drupal::entityTypeManager()->getStorage('user_role')->loadMultiple();
    $keys = array_keys($roles);
    if (!in_array('shopify_customer', $keys)) {
      $role = Role::create(array('id' => 'shopify_customer', 'label' => 'Shopify Customer'));
      $role->save();
      $role->grantPermission('view own shopify customer data');
    }
  }

  /**
   * Reload's store info cache.
   */
  public function reloadShopInfoCache() {
    Settings::shopInfo('domain', TRUE);
    \Drupal::messenger()->addStatus('Shop Info Cache reloaded!', TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Retrieve the configuration.
    $config = Settings::editableConfig();

    if ($form_state->getValue('allow_shopify_users') == TRUE) {
      $this->checkUserRoles();
    }

    $config->set('shop_url', $form_state->getValue('shop_url'))
      ->set('products_frequency', $form_state->getValue('products_frequency'))
      ->set('collections_frequency', $form_state->getValue('collections_frequency'))
      ->set('users_frequency', $form_state->getValue('users_frequency'))
      ->set('products_per_page', $form_state->getValue('products_per_page'))
      ->set('products_label', $form_state->getValue('products_label'))
      ->set('allow_shopify_users', $form_state->getValue('allow_shopify_users'))
      ->set('presale_text', $form_state->getValue('presale_text'))
      ->set('google_product_category', $form_state->getValue('google_product_category'))
      ->set('store_front_access_token', $form_state->getValue('store_front_access_token'))
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('api_secret', $form_state->getValue('api_secret'))
      ->set('api_password', $form_state->getValue('api_password'));

    $config->save();

    parent::submitForm($form, $form_state);
  }

}
