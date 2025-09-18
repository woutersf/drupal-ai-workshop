<?php

namespace Drupal\ai_agents\Plugin\AiFunctionCall;

use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai_agents\PluginInterfaces\AiAgentContextInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the list bundles function.
 */
#[FunctionCall(
  id: 'ai_agent:list_entity_types',
  function_name: 'ai_agent_list_entity_types',
  name: 'List Entity Types',
  description: 'This method lists all entity types.',
  group: 'information_tools',
  context_definitions: [
    'type_of_entity' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Type of Entity"),
      description: new TranslatableMarkup("If you specifically want just config or content entity types."),
      required: FALSE,
      constraints: [
        'AllowedValues' => [
          'config',
          'content',
        ],
      ],
    ),
  ],
)]
class ListEntityTypes extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

  /**
   * The entity type service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Load from dependency injection container.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): FunctionCallInterface|static {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai.context_definition_normalizer'),
    );
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->currentUser = $container->get('current_user');
    return $instance;
  }

  /**
   * The list.
   *
   * @var string
   */
  protected string $list = "";

  /**
   * {@inheritdoc}
   */
  public function execute() {
    // Set the highest level of access check.
    if (!$this->currentUser->hasPermission('administer permissions')) {
      throw new \Exception('You do not have permission to list entity types.');
    }
    // Collect the context values.
    $type_of_entity = $this->getContextValue('type_of_entity');
    // List all entity types.
    $entity_types = $this->entityTypeManager->getDefinitions();
    foreach ($entity_types as $entity_type) {
      if ($type_of_entity && (($entity_type instanceof ConfigEntityTypeInterface && $type_of_entity !== 'config') ||
        ($entity_type instanceof ContentEntityTypeInterface && $type_of_entity !== 'content'))) {
        continue;
      }
      $this->list .= $entity_type->id() . ', ' . $entity_type->getLabel() . PHP_EOL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getReadableOutput(): string {
    return $this->list;
  }

}
