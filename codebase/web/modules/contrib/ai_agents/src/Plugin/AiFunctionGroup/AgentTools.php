<?php

declare(strict_types=1);

namespace Drupal\ai_agents\Plugin\AiFunctionGroup;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\FunctionGroup;
use Drupal\ai\Service\FunctionCalling\FunctionGroupInterface;

/**
 * The Drupal agents.
 */
#[FunctionGroup(
  id: 'agent_tools',
  group_name: new TranslatableMarkup('Sub-Agent Tools'),
  description: new TranslatableMarkup('These exposes agents as tools that you can give a prompt and possibly some files to do something.'),
  weight: -10,
)]
final class AgentTools implements FunctionGroupInterface {
}
