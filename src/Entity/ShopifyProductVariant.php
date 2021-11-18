<?php

namespace Drupal\neg_shopify\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\file\FileInterface;
use Drupal\user\UserInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\neg_shopify\Entity\EntityInterface\ShopifyProductVariantInterface;
use Drupal\neg_shopify\Api\ShopifyService;
use Drupal\neg_shopify\Entity\EntityTrait\ShopifyEntityTrait;
use Drupal\negnet_utility\PersistentRenderCache;

/**
 * Defines the Shopify product variant entity.
 *
 * @ingroup shopify
 *
 * @ContentEntityType(
 *   id = "shopify_product_variant",
 *   label = @Translation("Shopify product variant"),
 *   handlers = {
 *     "view_builder" = "Drupal\neg_shopify\Entity\ViewBuilder\ShopifyProductVariantViewBuilder",
 *     "list_builder" = "Drupal\neg_shopify\Entity\ListBuilder\ShopifyProductVariantListBuilder",
 *     "views_data" = "Drupal\neg_shopify\Entity\ViewsData\ShopifyProductVariantViewsData",
 *
 *     "form" = {
 *       "default" = "Drupal\neg_shopify\Entity\Form\ShopifyProductVariantForm",
 *       "add" = "Drupal\neg_shopify\Entity\Form\ShopifyProductVariantForm",
 *       "edit" = "Drupal\neg_shopify\Entity\Form\ShopifyProductVariantForm",
 *       "delete" = "Drupal\neg_shopify\Entity\Form\ShopifyProductVariantDeleteForm",
 *     },
 *     "access" = "Drupal\neg_shopify\Entity\AccessControlHandler\ShopifyProductVariantAccessControlHandler",
 *   },
 *   base_table = "shopify_product_variant",
 *   admin_permission = "administer ShopifyProductVariant entity",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "title",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/admin/shopify_product_variant/{shopify_product_variant}",
 *     "edit-form" = "/admin/shopify_product_variant/{shopify_product_variant}/edit",
 *     "delete-form" = "/admin/shopify_product_variant/{shopify_product_variant}/delete"
 *   },
 *   field_ui_base_route = "shopify_product_variant.settings"
 * )
 */
class ShopifyProductVariant extends ContentEntityBase implements ShopifyProductVariantInterface {
  use EntityChangedTrait;
  use ShopifyEntityTrait;

  /**
   * {@inheritdoc}
   */
  public function save() {
    parent::save();

    $product = $this->getProduct();
    if ($product) {
      // Updates xml_listing cache.
      PersistentRenderCache::getCachedView($this, 'xml_listing', TRUE);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    // Clear xml_listing cache.
    PersistentRenderCache::clearCachedView($this, 'xml_listing');

    if ($this->image instanceof FileInterface) {
      // Ensure we delete this variant's image.
      $this->image->delete();
    }

    parent::delete();
  }

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    $values = self::formatValues($values);
    parent::preCreate($storage_controller, $values);
    $values += [
      'user_id' => \Drupal::currentUser()->id(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  private static function formatValues(array $values) {
    if (isset($values['id'])) {
      // We don't want to set the incoming product_id as the entity ID.
      $values['variant_id'] = $values['id'];
      unset($values['id']);
    }

    // Don't need product_id.
    unset($values['product_id']);

    // Format timestamps properly.
    self::formatDatetimeAsTimestamp([
      'created_at',
      'updated_at',
    ], $values);

    // Setup image.
    if (isset($values['image']) && !empty($values['image'])) {
      $file = self::setupProductImage($values['image']->src);
      if ($file instanceof FileInterface) {
        $values['image'] = [
          'target_id' => $file->id(),
          'alt' => $values['image']->alt,
        ];
      }
    }
    else {
      $values['image'] = NULL;
    }

    // Ensure inventory_quantity is not a negative number when we aren't tracking inventory at all.
    if (isset($values['inventory_management']) && $values['inventory_management'] === NULL && $values['inventory_quantity'] < 0) {
      // Inventory tracking is disabled, just set quantity to 1.
      $values['inventory_quantity'] = 1;
    }

    return $values;
  }

  /**
   * Checks to see is variant is available.
   */
  public function isAvailable() {
    $policy = $this->get('inventory_policy')->value;
    $quantityAvailable = $this->get('inventory_quantity')->value;

    if ($policy == 'continue') {
      return TRUE;
    }

    if ($quantityAvailable > 0) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function update(array $values = []) {
    $entity_id = $this->id();
    $values = self::formatValues($values);
    foreach ($values as $key => $value) {
      if ($this->__isset($key)) {
        $this->set($key, $value);
      }
    }
    $this->set('id', $entity_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormattedOptions() {
    $options = [
      $this->option1->value,
      $this->option2->value,
      $this->option3->value,
    ];
    $options = array_combine($options, $options);
    return array_filter($options, 'strlen');
  }

  /**
   * Loads an array of item views.
   */
  public static function loadViews($variants, $view_mode = 'cart', $defaultContext = TRUE, $persistent = FALSE) {
    $views = [];
    foreach ($variants as $variant) {
      if ($persistent) {
        $views[] = PersistentRenderCache::getCachedView($variant, $view_mode);
      }
      else {
        $views[] = $variant->loadView($view_mode, $defaultContext);
      }
    }

    return $views;
  }

  /**
   * Loads a view array.
   */
  public function loadView(string $style = 'cart', $defaultContext = TRUE) {

    $entity = $this;

    if ($defaultContext === FALSE) {
      $rendered_view = NULL;
      \Drupal::service('renderer')->executeInRenderContext(new RenderContext(), function () use (&$entity, $style, &$rendered_view) {
        $build = \Drupal::entityTypeManager()->getViewBuilder('shopify_product_variant')->view($entity, $style);
        $rendered_view = render($build);
      });
    }
    else {
      $build = \Drupal::entityTypeManager()->getViewBuilder('shopify_product_variant')->view($entity, $style);
      $rendered_view = $build;
    }

    return $rendered_view;
  }

  /**
   * Loads a variant by it's sku.
   *
   * @param string $sku
   *   Sku as string.
   *
   * @return ShopifyProductVariant
   *   Product variant.
   */
  public static function loadBySku($sku) {
    $variants = (array) self::loadByProperties(['sku' => $sku]);
    return reset($variants);
  }

  /**
   * Loads a variant by it's variant_id.
   *
   * @param string $variant_id
   *   Variant ID as string or int.
   *
   * @return ShopifyProductVariant
   *   Product variant.
   */
  public static function loadByVariantId($variant_id) {
    $variants = (array) self::loadByProperties(['variant_id' => $variant_id]);
    return reset($variants);
  }

  /**
   * Load variants by their properties.
   *
   * @param array $props
   *   Key/value pair of properties to query by.
   *
   * @return ShopifyProductVariant[]
   *   Products.
   */
  public static function loadByProperties(array $props = []) {
    return \Drupal::entityTypeManager()
      ->getStorage('shopify_product_variant')
      ->loadByProperties($props);
  }

  /**
   * Searches for variants.
   */
  public static function search(array $params) {
    $query = self::getSearchQuery($params);
    $result = $query->execute();
    $ids = [];
    $results = $result->fetchAllAssoc('id');

    foreach ($results as $result) {
      $ids[] = $result->id;
    }

    $ids = (count($ids) > 0) ? self::loadMultiple($ids) : [];
    return $ids;
  }

  /**
   * Searches for variants.
   */
  public static function getSearchQuery(array $params) {

    $fields = [
      'v' => ['id'],
      'product' => ['is_available'],
    ];

    $query = \Drupal::database()->select('shopify_product_variant', 'v')
      ->distinct(TRUE);

    $query->leftJoin('shopify_product__variants', 'variants', 'variants.variants_target_id = v.id');
    $query->leftJoin('shopify_product', 'product', 'product.id = variants.entity_id');
    $query->isNotNull('product.image__target_id');
    $query->isNotNull('product.published_at');
    $query->orderBy('product.is_available', 'DESC');

    // Handle requiring body.
    if (isset($params['require_body'])) {
      $query->isNotNull('product.body_html__value');
    }

    // Handle manual collections.
    if (isset($params['collection_id'])) {
      $query->leftJoin('shopify_product__collections', 'collections', 'collections.entity_id = product.id');
      $query->condition('collections.collections_target_id', $params['collection_id'], '=');
    }

    $show = isset($params['show']) ? $params['show'] : 'available';

    if ($show === 'available') {
      // Require active product.
      $query->condition('product.status', 1);

      // Add an or query for available or preorders.
      $group = $query->orConditionGroup();

      // Make sure is available.
      $group->condition('product.is_available', 1);

      // Or is a preorder.
      $group->condition('product.is_preorder', 1);

      $query->condition($group);
    }

    // Smart Collection.
    if (isset($params['collection_sort']) && isset($params['collection_sort']['rules'])) {
      // Set group type.
      $rules_group = $query->andConditionGroup();
      if ($params['collection_disjunctive']) {
        $rules_group = $query->orConditionGroup();
      }

      foreach ($params['collection_sort']['rules'] as $rule) {
        $field = FALSE;
        $relation = '=';

        switch ($rule['column']) {
          case 'title':
            $field = 'product.title';
            break;

          case 'vendor':
            $field = 'product.vendor';
            break;

          case 'type':
            $field = 'product.product_type';
            break;

          case 'tag':
            $field = 'term.name';
            $query->leftJoin('shopify_product__tags', 'tags', 'tags.entity_id = product.id');
            $query->leftJoin('taxonomy_term_field_data', 'term', 'term.tid = tags.tags_target_id');
            break;

          case 'variant_title':
            $field = 'v.title';
            break;

          case 'variant_compare_at_price':
            $field = 'v.compare_at_price';
            break;

          case 'variant_weight':
            $field = 'v.weight';
            break;

          case 'variant_inventory':
            $field = 'v.inventory_quantity';
            break;

          case 'variant_price':
            $field = 'v.price';
            break;
        }

        switch ($rule['relation']) {
          case 'equals':
            $relation = '=';
            break;

          case 'not_equals':
            $relation = '<>';
            break;

          case 'greater_than':
            $relation = '>';
            break;

          case 'less_than':
            $relation = '<';
            break;

          case 'starts_with':
            $relation = 'STARTS_WITH';
            break;

          case 'ends_with':
            $relation = 'ENDS_WITH';
            break;

          case 'contains':
            $relation = 'CONTAINS';
            break;
        }

        if ($field) {
          $rules_group->condition($field, $rule['condition'], $relation);
        }
      }

      $query->condition($rules_group);
    }

    // Filter for max-price.
    if (isset($params['max-price'])) {
      $query->condition('v.price', $params['max-price'], '<');
    }

    // Filter for max-price.
    if (isset($params['min-price'])) {
      $query->condition('v.price', $params['min-price'], '>');
    }

    // Set sort order.
    if (isset($params['sort'])) {
      $sort = $params['sort'];
    }
    else {
      $sort = Settings::defaultSortOrder();
    }

    $parts = explode('-', $sort);

    // Default.
    $sort = ['product.title'];
    $fields['product'][] = 'title';

    if (isset($parts[0])) {
      switch ($parts[0]) {
        case 'price':
          $sort = ['v.price', 'product.title'];
          $fields['v'][] = 'price';
          break;

        case 'date':
          $sort = ['product.created_at', 'product.title'];
          $fields['product'][] = 'created_at';
          break;

        case 'title':
          $sort = ['product.title'];
          break;
      }
    }

    // Default.
    $direction = 'DESC';
    if (isset($parts[1])) {
      switch ($parts[1]) {
        case 'ascending':
          $direction = 'ASC';
          break;

        case 'descending':
          $direction = 'DESC';
          break;
      }
    }

    foreach ($sort as $option) {
      $query->orderBy($option, $direction);
    }

    foreach ($fields as $table => $columns) {
      $columns = array_unique($columns);
      $query->fields($table, $columns);
    }

    return $query;
  }

  /**
   * Converts a shopify product id to a graph ql id.
   */
  public static function idToGraphQlId($id, $base64Encoded = FALSE) {
    return ShopifyService::idToGraphQlId($id, 'ProductVariant', $base64Encoded);
  }

  /**
   * Converts a shopify graph ql product id to a rest id.
   */
  public static function graphQlIdToId($id, $base64Encoded = FALSE) {
    return ShopifyService::graphQlIdToId($id, 'ProductVariant', $base64Encoded);
  }

  /**
   * Gets product url.
   */
  public function getProductUrl() {
    $product = $this->getProduct();

    $url = NULL;
    \Drupal::service('renderer')->executeInRenderContext(new RenderContext(), function () use (&$product, &$url) {
      $url = $product->toUrl();
      $url->setOption('query', [
        'variant_id' => $this->variant_id->value,
      ]);
      $url = $url->toString();
    });

    return $url;
  }

  /**
   * Returns the associated parent product.
   *
   * @return \Drupal\shopify\Entity\ShopifyProduct
   *   Product.
   */
  public function getProduct() {
    return ShopifyProduct::loadByVariantId($this->variant_id->value);
  }

  /**
   * {@inheritdoc}
   */
  public function url($rel = 'canonical', $options = []) {
    // While self::toUrl() will throw an exception if the entity has no id,
    // the expected result for a URL is always a string.
    if ($this->id() === NULL || !$this->hasLinkTemplate($rel)) {
      return '';
    }
    // URL should point to the product page with a variant_id param set.
    $product = $this->getProduct();
    if ($product instanceof ShopifyProduct) {
      $options['query'] = ['variant_id' => $this->variant_id->value];
      $uri = $product->toUrl($rel);
      $uri->setOptions($options);
      return $uri->toString();
    }
    else {
      return '';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('user_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('user_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('user_id', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * Get's variant size.
   */
  public function getAttribute($name) {
    $optionNames = array_map('strtolower', $this->getProduct()->getOptionNames());

    $key = FALSE;
    // Find the attribute.
    foreach ($optionNames as $index => $optionName) {
      if (strstr($optionName, $name) !== FALSE) {
        $key = $index;
        break;
      }
    }

    if ($key !== FALSE) {
      $optionVar = 'option' . ($key + 1);
      $value = $this->$optionVar->value;
      return $value;
    }

    return NULL;
  }

  /**
   * Get's selected options.
   */
  public function getSelectedOptions() {
    $options = [];

    $product = $this->getProduct();
    $optionNames = $product->getOptionNames();
    foreach ($optionNames as $i => $name) {
      $optionVar = 'option' . ($i + 1);
      $value = $this->$optionVar->value;
      $options[] = [
        'name' => $name,
        'value' => $value,
      ];
    }

    return $options;
  }

  /**
   * Formats a selected options array to a storefront selectedOptions object.
   */
  protected function formatSelectedOptionForStoreFront($option) {
  }

  /**
   * Fetchs the shopify storefront api id for this variant.
   */
  public function getStoreFrontId() {
    $variantId = $this->get('variant_id')->value;
    return self::idToGraphQlId($variantId, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the Shopify product variant entity.'))
      ->setReadOnly(TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the Shopify product variant entity.'))
      ->setReadOnly(TRUE);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of author of the Shopify product variant entity.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setDefaultValueCallback('Drupal\node\Entity\Node::getCurrentUserId')
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setDescription(t('The title of the Shopify product variant entity.'))
      ->setRequired(TRUE)
      ->setSettings([
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['variant_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Variant ID'))
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['storefront_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('StoreFront ID'))
      ->setDefaultValue('')
      ->setReadOnly(TRUE);

    $fields['langcode'] = BaseFieldDefinition::create('language')
      ->setLabel(t('Language code'))
      ->setDescription(t('The language code for the Shopify product variant entity.'));

    $fields['inventory_management'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Inventory management'))
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['inventory_policy'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Inventory policy'))
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 6,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 6,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['sku'] = BaseFieldDefinition::create('string')
      ->setLabel(t('SKU'))
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 7,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 7,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['fulfillment_service'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Fulfillment service'))
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['barcode'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Barcode'))
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 3,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['grams'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Grams'))
      ->setSettings([
        'unsigned' => TRUE,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'integer',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['inventory_quantity'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Inventory quantity'))
      ->setSettings([
        'unsigned' => FALSE,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'integer',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['old_inventory_quantity'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Old inventory quantity'))
      ->setSettings([
        'unsigned' => FALSE,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'integer',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['position'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Position'))
      ->setSettings([
        'unsigned' => TRUE,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'integer',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['weight'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('Weight'))
      ->setSettings([
        'precision' => 10,
        'scale' => 2,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'shopify_weight',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['weight_unit'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Weight unit'))
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 8,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 8,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['price'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('Price'))
      ->setSettings([
        'precision' => 10,
        'scale' => 2,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'shopify_price',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['compare_at_price'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('Compare at price'))
      ->setSettings([
        'precision' => 10,
        'scale' => 2,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'shopify_price',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['taxable'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Taxable'))
      ->setSettings([
        'on_label' => 'Taxable',
        'off_label' => 'Not taxable',
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'boolean',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['requires_shipping'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Requires shipping'))
      ->setSettings([
        'on_label' => 'Require shipping',
        'off_label' => 'Do not require shipping',
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'boolean',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['image'] = BaseFieldDefinition::create('image')
      ->setLabel(t('Image'))
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'responsive_image',
        'settings' => ['responsive_image_style' => 'rs_image'],
        'weight' => 2,
      ])
      ->setDisplayOptions('form', [
        'type' => 'image',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['dynamic_product_image'] = BaseFieldDefinition::create('image')
      ->setLabel(t('Dynamic Product Image'))
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'responsive_image',
        'weight' => -40,
        'settings' => ['responsive_image_style' => 'rs_5x4'],
      ])
      ->setDisplayOptions('form', [
        'type' => 'image_image',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setComputed(TRUE)
      ->setClass('\Drupal\neg_shopify\TypedData\DynamicProductVariantImage');

    $fields['option1'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Option 1'))
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['option2'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Option 2'))
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['option3'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Option 3'))
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last udpated.'));

    $fields['created_at'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the product was created.'));

    $fields['updated_at'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Updated'))
      ->setDescription(t('The time that the product was last updated.'));

    // @todo option_values.
    return $fields;
  }

}
