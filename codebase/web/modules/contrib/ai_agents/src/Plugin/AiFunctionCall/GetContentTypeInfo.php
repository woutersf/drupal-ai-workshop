<?php

namespace Drupal\ai_agents\Plugin\AiFunctionCall;

use Drupal\Core\Entity\EntityFieldManagerInterface;
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
 * Plugin implementation of the get content type info function.
 */
#[FunctionCall(
  id: 'ai_agent:get_content_type_info',
  function_name: 'get_content_type_info',
  name: 'Get Content Type Info',
  description: 'This method gets all the base data for an content type, if its sticky, published by default etc.',
  group: 'information_tools',
  module_dependencies: ['node'],
  context_definitions: [
    'node_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Node Type"),
      description: new TranslatableMarkup("The data name of the node type to get more information about."),
      required: TRUE
    ),
  ],
)]
class GetContentTypeInfo extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * The current user service.
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
    $instance->entityFieldManager = $container->get('entity_field.manager');
    $instance->currentUser = $container->get('current_user');
    return $instance;
  }

  /**
   * The information.
   *
   * @var string
   */
  protected string $information = "";

  /**
   * {@inheritdoc}
   */
  public function execute() {
    // Collect the context values.
    $node_type_id = $this->getContextValue('node_type');
    // Check permissions for the current user.
    if (!$this->currentUser->hasPermission('administer content types')) {
      throw new \Exception('You do not have permission to administer content types.');
    }
    /** @var \Drupal\node\Entity\NodeType $node_type */
    $node_type = $this->entityTypeManager->getStorage('node_type')->load($node_type_id);
    // If the node type does not exist, return an error message.
    if (!$node_type) {
      $this->information = 'Node type with data name "' . $node_type_id . '" does not exist.';
      return;
    }
    if ($node_type) {
      // Show all the configurations.
      $this->information .= $node_type->label() . ' - dataname: ' . $node_type->id() . "\n";
      $this->information .= 'Description: ' . $node_type->getDescription() . "\n";
      $this->information .= 'New revision: ' . ($node_type->get('new_revision') ? 'true' : 'false') . "\n";
      $this->information .= 'Preview mode: ' . ($node_type->getPreviewMode() ? 'true' : 'false') . "\n";
      $this->information .= 'Display submitted: ' . ($node_type->displaySubmitted() ? 'true' : 'false') . "\n";
      $fields = $this->entityFieldManager->getFieldDefinitions('node', $node_type_id);
      foreach ([
        'status' => 'Published by default: ',
        'promote' => 'Promoted to front page by default: ',
        'sticky' => 'Sticky enabled by default: ',
      ] as $key => $name) {
        $state = $fields[$key]->getConfig($node_type_id)->getDefaultValueLiteral()[0]['value'] ?? 0;
        $this->information .= $name . ($state ? 'true' : 'false') . "\n";
      }
      $this->information .= "\n";
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getReadableOutput(): string {
    return $this->information;
  }

}
