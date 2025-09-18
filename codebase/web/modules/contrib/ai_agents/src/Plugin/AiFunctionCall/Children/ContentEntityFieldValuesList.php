<?php

namespace Drupal\ai_agents\Plugin\AiFunctionCall\Children;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;

/**
 * Wrapper of the array of values for an entity.
 */
#[FunctionCall(
  id: 'ai_agent:content_entity_field_values_list',
  function_name: 'ai_agents_content_entity_field_values_list',
  name: 'Content Entity Field Values List',
  description: 'This is a wrapper for a field.',
  group: 'entity_tools',
  context_definitions: [
    'field_name' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Field Name"),
      description: new TranslatableMarkup("The field you are trying to save to."),
      required: TRUE
    ),
    'field_values' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup("Field Values"),
      description: new TranslatableMarkup("The entity array to seed the entity with."),
      required: TRUE,
      multiple: TRUE,
      constraints: [
        'ComplexToolItems' => ContentEntityFieldValue::class,
      ],
    ),
  ],
)]
class ContentEntityFieldValuesList extends FunctionCallBase {

}
