<?php

namespace Drupal\ai_agents\Plugin\AiFunctionCall\Children;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;

/**
 * Wrapper of one value key and its value.
 */
#[FunctionCall(
  id: 'ai_agent:content_entity_field_value',
  function_name: 'ai_agents_content_entity_field_value',
  name: 'Content Entity Field Value',
  description: 'This is a wrapper for a field value.',
  group: 'entity_tools',
  context_definitions: [
    'value_name' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Value Name"),
      description: new TranslatableMarkup("The field key that needs to be filled in."),
      required: TRUE,
    ),
    'values' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup("Values"),
      description: new TranslatableMarkup("The value to fill in the field."),
      required: TRUE,
      multiple: TRUE,
    ),
  ],
)]
class ContentEntityFieldValue extends FunctionCallBase {

}
