<?php

namespace Drupal\neg_shopify\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\file\FileInterface;
use Drupal\user\UserInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\taxonomy\Entity\Term;
use Drupal\neg_shopify\Entity\EntityInterface\ShopifyVendorInterface;
use Drupal\neg_shopify\Entity\ShopifyProduct;
use Drupal\neg_shopify\Entity\ShopifyProductSearch;
use Drupal\neg_shopify\Settings;
use Drupal\neg_shopify\Entity\EntityTrait\ShopifyEntityTrait;

/**
 * Defines the Shopify vendor entity.
 *
 * @ingroup shopify
 *
 * @ContentEntityType(
 *   id = "shopify_vendor",
 *   label = @Translation("Shopify Vendor"),
 *   handlers = {
 *     "view_builder" = "Drupal\neg_shopify\Entity\ViewBuilder\ShopifyVendorViewBuilder",
 *     "list_builder" = "Drupal\neg_shopify\Entity\ListBuilder\ShopifyVendorListBuilder",
 *     "views_data" = "Drupal\neg_shopify\Entity\ViewsData\ShopifyVendorViewsData",
 *
 *     "access" = "Drupal\neg_shopify\Entity\AccessControlHandler\ShopifyVendorAccessControlHandler",
 *   },
 *   base_table = "shopify_vendor",
 *   admin_permission = "administer ShopifyVendor entity",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "title",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/admin/shopify_vendor/{shopify_vendor}",
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
   * Searches for vendors.
   */
  public static function search(int $page = 0, int $perPage = 0, $params = []) {

    $defaults = [
      'tags' => [],
      'types' => [],
    ];
    $params = array_merge($defaults, $params);

    $query = \Drupal::entityTypeManager()
      ->getStorage('shopify_vendor')
      ->getQuery();

    if (count($params['tags']) > 0) {
      $query->condition('tags', $params['tags'], 'IN');
    }

    if (count($params['types']) > 0) {
      $query->condition('type', $params['types'], 'IN');
    }

    if ($perPage !== 0) {
      $query->range($page * $perPage, $perPage);
    }

    return $query;
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

    $fields['slug'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Slug'))
      ->setDescription(t('The slug of the Shopify vendor entity.'))
      ->setRequired(TRUE)
      ->setSettings([
        'text_processing' => 0,
      ])
      ->setDefaultValue('');

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setDescription(t('The title of the Shopify vendor entity.'))
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

    $fields['type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Type'))
      ->setDescription(t('The type of the Shopify vendor entity.'))
      ->setRequired(TRUE)
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
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
        'weight' => -20,
        'settings' => ['link' => TRUE],
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete_tags',
        'weight' => -25,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

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
