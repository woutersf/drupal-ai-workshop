<?php

declare(strict_types=1);

namespace Drupal\ai_agents\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\ai_agents\AiAgentInterface;

/**
 * Defines the AI Agent entity type.
 *
 * @ConfigEntityType(
 *   id = "ai_agent",
 *   label = @Translation("AI Agent"),
 *   label_collection = @Translation("AI Agents"),
 *   label_singular = @Translation("AI Agent"),
 *   label_plural = @Translation("AI Agents"),
 *   label_count = @PluralTranslation(
 *     singular = "@count AI Agent",
 *     plural = "@count AI Agents",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\ai_agents\AiAgentListBuilder",
 *     "form" = {
 *       "add" = "Drupal\ai_agents\Form\AiAgentForm",
 *       "edit" = "Drupal\ai_agents\Form\AiAgentForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *   },
 *   config_prefix = "ai_agent",
 *   admin_permission = "administer ai_agent",
 *   links = {
 *     "collection" = "/admin/structure/ai-agent",
 *     "add-form" = "/admin/structure/ai-agent/add",
 *     "edit-form" = "/admin/structure/ai-agent/{ai_agent}",
 *     "delete-form" = "/admin/structure/ai-agent/{ai_agent}/delete",
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "default_information_tools",
 *     "system_prompt",
 *     "secured_system_prompt",
 *     "tools",
 *     "tool_usage_limits",
 *     "tool_settings",
 *     "orchestration_agent",
 *     "triage_agent",
 *     "max_loops",
 *     "masquerade_roles",
 *     "exclude_users_role",
 *   },
 * )
 */
final class AiAgent extends ConfigEntityBase implements AiAgentInterface {

  /**
   * The example ID.
   */
  protected string $id;

  /**
   * The example label.
   */
  protected string $label;

  /**
   * The example description.
   */
  protected string $description;

  /**
   * The dynamic context tools.
   */
  protected ?string $default_information_tools = NULL;

  /**
   * The system prompt (agent instructions).
   */
  protected string $system_prompt;

  /**
   * The secured system prompt that can contain secure instructions.
   */
  protected ?string $secured_system_prompt = NULL;

  /**
   * The tools that can be used.
   */
  protected array $tools;

  /**
   * The tool usage limits.
   */
  protected ?array $tool_usage_limits = NULL;

  /**
   * The tool settings.
   */
  protected ?array $tool_settings = NULL;

  /**
   * Is this an orchestration agent.
   */
  protected bool $orchestration_agent;

  /**
   * Is this a triage agent.
   */
  protected bool $triage_agent;

  /**
   * The max amount of loops.
   */
  protected int $max_loops = 3;

  /**
   * The agent masquerade roles.
   */
  protected array $masquerade_roles = [];

  /**
   * Do not use users role.
   */
  protected bool $exclude_users_role = FALSE;

}
