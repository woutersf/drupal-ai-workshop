<?php

declare(strict_types=1);

namespace Drupal\ai_search_block_log\Entity;

use Drupal\ai_search_block_log\AISearchBlockLogInterface;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the ai search block log entity class.
 *
 * @ContentEntityType(
 *   id = "ai_search_block_log",
 *   label = @Translation("AI Search Block Log"),
 *   label_collection = @Translation("AI Search Block Logs"),
 *   label_singular = @Translation("ai search block log"),
 *   label_plural = @Translation("ai search block logs"),
 *   label_count = @PluralTranslation(
 *     singular = "@count ai search block logs",
 *     plural = "@count ai search block logs",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\ai_search_block_log\AISearchBlockLogListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "default" = "Drupal\ai_search_block_log\Form\AiSearchBlockLogForm",
 *       "add" = "Drupal\ai_search_block_log\Form\AiSearchBlockLogForm",
 *       "edit" = "Drupal\ai_search_block_log\Form\AiSearchBlockLogForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" = "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "ai_search_block_log",
 *   admin_permission = "administer ai_search_block_log",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "id",
 *     "uid" = "uid",
 *   },
 *   links = {
 *     "collection" = "/admin/content/ai-search-block-log",
 *     "add-form" = "/ai-search-block-log/add",
 *     "canonical" = "/ai-search-block-log/{ai_search_block_log}",
 *     "edit-form" = "/ai-search-block-log/{ai_search_block_log}/edit",
 *     "delete-form" = "/ai-search-block-log/{ai_search_block_log}/delete",
 *     "delete-multiple-form" = "/admin/content/ai-search-block-log/delete-multiple",
 *   },
 *   field_ui_base_route = "entity.ai_search_block_log.settings",
 * )
 */
final class AiSearchBlockLog extends ContentEntityBase implements AISearchBlockLogInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the log entity.'))
      ->setReadOnly(TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Created by'))
      ->setDescription(t('The username of the entity author.'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 0,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['block_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Block ID'))
      ->setDescription(t('The ID of the block.'))
      ->setRequired(TRUE)
      ->setSettings([
        'default_value' => '',
        'max_length' => 255,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -1,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created on'))
      ->setDescription(t('The time the item was created.'))
      ->setTranslatable(FALSE)
      ->setRevisionable(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 99,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'timestamp',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['expiry'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Expires'))
      ->setDescription(t('The time the item will expire.'))
      ->setTranslatable(FALSE)
      ->setRevisionable(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 100,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'timestamp',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['question'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Question'))
      ->setDescription(t('The question.'))
      ->setSettings([
        'default_value' => '',
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['prompt_used'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Prompt Used'))
      ->setDescription(t('The prompt used for the decision.'))
      ->setSettings([
        'default_value' => '',
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['response_given'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Response Given'))
      ->setDescription(t('The response given by the agent.'))
      ->setSettings([
        'default_value' => '',
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['detailed_output'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Detailed Output'))
      ->setDescription(t('The detailed output of the decision.'))
      ->setSettings([
        'default_value' => '',
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['score'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Score'))
      ->setDescription(t('The user score.'))
      ->setSettings([
        'default_value' => '',
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setSetting('unsigned', TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['feedback'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Detailed feedback'))
      ->setDescription(t('The detailed feedback.'))
      ->setSettings([
        'default_value' => '',
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', FALSE);
    return $fields;
  }

}
