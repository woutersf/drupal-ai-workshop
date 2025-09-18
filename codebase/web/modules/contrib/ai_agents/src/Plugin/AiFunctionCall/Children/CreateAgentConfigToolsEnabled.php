<?php

namespace Drupal\ai_agents\Plugin\AiFunctionCall\Children;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;

/**
 * Wrapper of the array of values for the tools.
 */
#[FunctionCall(
  id: 'ai_agent:create_agent_config_tools_enabled',
  function_name: 'ai_agents_create_agent_config_tools_enabled',
  name: 'Tools',
  description: 'The list of enabled tools.',
  context_definitions: [
    'key' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Tools Id"),
      description: new TranslatableMarkup("The id of the tool."),
      required: TRUE
    ),
    'value' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup("Enabled"),
      description: new TranslatableMarkup("If the tool is enabled."),
      required: TRUE
    ),
  ],
)]
class CreateAgentConfigToolsEnabled extends FunctionCallBase {

}
