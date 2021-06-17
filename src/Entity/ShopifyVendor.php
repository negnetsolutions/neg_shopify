<?php

namespace Drupal\neg_shopify\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\UserInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\taxonomy\Entity\Term;
use Drupal\neg_shopify\Entity\EntityInterface\ShopifyVendorInterface;
use Drupal\neg_shopify\Settings;
use Drupal\neg_shopify\Entity\EntityTrait\ShopifyEntityTrait;
use Drupal\neg_shopify\Event\VendorSearchQueryEvent;

/**
 * Defines the Shopify vendor entity.
 *
 * @ingroup shopify
 *
 * @ContentEntityType(
 *   id = "shopify_vendor",
 *   label = @Translation("Shopify Vendor"),
 *   handlers = {
 *     "storage_schema" = "Drupal\neg_shopify\Entity\StorageSchema\ShopifyVendorStorageSchema",
 *     "view_builder" = "Drupal\neg_shopify\Entity\ViewBuilder\ShopifyVendorViewBuilder",
 *     "list_builder" = "Drupal\neg_shopify\Entity\ListBuilder\ShopifyVendorListBuilder",
 *     "views_data" = "Drupal\neg_shopify\Entity\ViewsData\ShopifyVendorViewsData",
 *
 *     "form" = {
 *       "default" = "Drupal\neg_shopify\Entity\Form\ShopifyVendorForm",
 *       "edit" = "Drupal\neg_shopify\Entity\Form\ShopifyVendorForm",
 *     },
 *
 *     "access" = "Drupal\neg_shopify\Entity\AccessControlHandler\ShopifyVendorAccessControlHandler",
 *   },
 *   base_table = "shopify_vendor",
 *   admin_permission = "administer shopify vendor entity",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "title",
 *     "status" = "status",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/admin/shopify_vendor/{shopify_vendor}",
 *     "edit-form" = "/admin/shopify_vendor/{shopify_vendor}/edit",
 *   },
 *   field_ui_base_route = "shopify_vendor.settings"
 * )
 */
class ShopifyVendor extends ContentEntityBase implements ShopifyVendorInterface {
  use EntityChangedTrait;
  use ShopifyEntityTrait;

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
    $terms = [];
    foreach ($values['tags'] as $tag) {
      // Find out if this tag already exists.
      $term = Term::load($tag);
      if ($term) {
        $terms[] = $term;
      }
    }

    $values['tags'] = $terms;

    return $values;
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
   * Get's full list of vendors.
   */
  public static function getVendorNames() {
    $vendors = [];
    foreach (self::loadMultiple() as $vendor) {
      $vendors[] = $vendor->get('title')->value;
    }
    return $vendors;
  }

  /**
   * Filters out vendors from tags.
   */
  public static function filterTagsForVendors(&$tags) {
    $vendors = self::getVendorNames();
    $vendors = array_map('strtolower', $vendors);
    $vendorsFound = [];
    $vendorsIndexesFound = [];

    for ($i = 0; $i < count($tags); $i++) {
      $tag = $tags[$i];
      if (array_search(strtolower($tag), $vendors) !== FALSE) {
        $vendorsFound[] = $tag;
        $vendorsIndexesFound[] = $i;
      }
    }

    foreach ($vendorsIndexesFound as $i) {
      unset($tags[$i]);
    }

    $tags = array_values($tags);
    return $vendorsFound;
  }

  /**
   * Searches for vendors.
   */
  public static function search(int $page = 0, int $perPage = 0, $params = []) {

    $defaults = [
      'tags' => [],
      'types' => [],
    ];
    $params = array_merge($defaults, $params);

    $fields = [
      'v' => ['id', 'title'],
    ];

    $query = \Drupal::database()->select('shopify_vendor', 'v')
      ->distinct(TRUE);

    $user = \Drupal::currentUser();
    if (!$user->hasPermission('view shopify toolbar')) {
      // Require published vendor.
      $query->condition('v.status', 1);

      // Require vendor with active products.
      $query->leftJoin('shopify_product', 'p', 'p.vendor_slug = v.slug');

      $query->condition('p.status', 1);
      $query->isNotNull('p.published_at');

      $group = $query->orConditionGroup();
      $group->condition('p.is_available', 1);
      $group->condition('p.is_preorder', 1);
      $query->condition($group);

      $query->addExpression('COUNT(p.id)', 'p_id_count');
      $query->groupBy('v.id');
    }

    if (count($params['tags']) > 0) {
      $query->leftJoin('shopify_vendor__tags', 'tags', 'tags.entity_id = v.id');
      $query->condition('tags.tags_target_id', $params['tags'], 'IN');
      $fields['tags'][] = 'tags_target_id';
    }

    if (count($params['types']) > 0) {
      $query->leftJoin('shopify_vendor__type', 'vtypes', 'vtypes.entity_id = v.id');
      $query->condition('vtypes.type_value', $params['types'], 'IN');
      $fields['vtypes'][] = 'type_value';
    }

    if ($perPage !== 0) {
      $query->range($page * $perPage, $perPage);
    }

    foreach ($fields as $table => $columns) {
      $columns = array_unique($columns);
      $query->fields($table, $columns);
    }

    // Throw an event to allow query to be altered.
    $event = new VendorSearchQueryEvent($query);
    \Drupal::service('event_dispatcher')->dispatch(VendorSearchQueryEvent::ALTERSEARCHQUERY, $event);

    // Add default order by.
    if (count($query->getOrderBy()) === 0) {
      $query->orderBy('v.title', 'ASC');
    }

    return $query;
  }

  /**
   * Get's first X number of vendor products.
   */
  public function getProducts($limit = 1, $offset = 0) {
    $params = [
      'sort' => Settings::defaultSortOrder(),
      'vendor_slug' => $this->get('slug')->value,
    ];

    $search = new ShopifyProductSearch($params);
    $products = $search->search($offset, $limit);

    return $products;
  }

  /**
   * Get's product count.
   */
  public function getProductCount($onlyAvailable = FALSE) {
    $params = [
      'sort' => Settings::defaultSortOrder(),
      'vendor_slug' => $this->get('slug')->value,
    ];

    if ($onlyAvailable) {
      $params['show'] = 'available';
    }

    $search = new ShopifyProductSearch($params);
    return $search->count();
  }

  /**
   * Renders product json.
   */
  public function renderProductJson($sortOrder = FALSE, $page = 0, $perPage = FALSE) {
    if ($sortOrder === FALSE) {
      $sortOrder = Settings::defaultSortOrder();
    }
    if ($perPage === FALSE) {
      $perPage = Settings::productsPerPage();
    }

    $params = [
      'sort' => $sortOrder,
      'vendor_slug' => $this->get('slug')->value,
    ];

    $tags = ['shopify_product_list', 'shopify_vendor:' . $this->id()];

    $search = new ShopifyProductSearch($params);

    $total = $search->count();
    $products = $search->search($page, $perPage);

    return [
      'count' => $total,
      'items' => ShopifyProduct::loadView($products, 'store_listing', FALSE),
    ];
  }

  /**
   * Loads a view array.
   */
  public function loadView(string $style = 'teaser', $defaultContext = TRUE) {

    $build = \Drupal::entityTypeManager()->getViewBuilder('shopify_vendor')->view($this, $style);

    if ($defaultContext === FALSE) {
      $rendered_view = NULL;
      \Drupal::service('renderer')->executeInRenderContext(new RenderContext(), function () use (&$build, &$rendered_view) {
        $rendered_view = render($build);
      });
    }
    else {
      $rendered_view = $build;
    }

    return $rendered_view;
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
  public static function loadBySlug($slug) {
    $variants = (array) self::loadByProperties(['slug' => $slug]);
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
      ->getStorage('shopify_vendor')
      ->loadByProperties($props);
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
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the Shopify vendor entity.'))
      ->setReadOnly(TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the Shopify vendor entity.'))
      ->setReadOnly(TRUE);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of author of the Shopify vendor entity.'))
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
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['slug'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Slug'))
      ->setDescription(t('The slug of the Shopify vendor entity.'))
      ->setRequired(TRUE)
      ->setReadOnly(TRUE)
      ->setSettings([
        'text_processing' => 0,
      ])
      ->setDefaultValue('');

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setDescription(t('The title of the Shopify vendor entity.'))
      ->setRequired(TRUE)
      ->setReadOnly(TRUE)
      ->setSettings([
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Type'))
      ->setDescription(t('The type of the Shopify vendor entity.'))
      ->setRequired(TRUE)
      ->setReadOnly(TRUE)
      ->setSettings([
        'text_processing' => 0,
      ])
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDefaultValue('');

    $fields['tags'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Tags'))
      ->setDescription(t('Vendor tags.'))
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('target_bundles', ['shopify_tags' => 'shopify_tags'])
      ->setSetting('handler', 'default:taxonomy_term')
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setReadOnly(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
        'weight' => -20,
        'settings' => ['link' => TRUE],
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Published'))
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => TRUE,
        ],
        'weight' => -10,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['description'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Description'))
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'text_default',
        'weight' => -30,
      ])
      ->setDisplayOptions('form', [
        'type' => 'text_textfield',
        'weight' => -2,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['thumbnail'] = BaseFieldDefinition::create('image')
      ->setLabel(t('Thumbnail Image'))
      ->setDefaultValue('')
      ->setDescription(t('Set a vendor thumbnail with this field. Leave blank to use the most recent available product as the thumbnail.'))
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'responsive_image',
        'weight' => -40,
        'settings' => ['responsive_image_style' => 'rs_image'],
      ])
      ->setDisplayOptions('form', [
        'type' => 'image_image',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['dynamic_thumbnail'] = BaseFieldDefinition::create('image')
      ->setLabel(t('Dynamic Thumbnail Image'))
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'responsive_image',
        'weight' => -40,
        'settings' => ['responsive_image_style' => 'rs_image'],
      ])
      ->setDisplayOptions('form', [
        'type' => 'image_image',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', FALSE)
      ->setComputed(TRUE)
      ->setClass('\Drupal\neg_shopify\TypedData\DynamicVendorImage');

    $fields['langcode'] = BaseFieldDefinition::create('language')
      ->setLabel(t('Language code'))
      ->setDescription(t('The language code for the Shopify vendor entity.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last udpated.'));

    $fields['created_at'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the product was created.'));

    $fields['updated_at'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Updated'))
      ->setDescription(t('The time that the product was last updated.'));

    return $fields;
  }

}
