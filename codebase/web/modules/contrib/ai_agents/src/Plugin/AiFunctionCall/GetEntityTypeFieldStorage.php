<?php

namespace Drupal\ai_agents\Plugin\AiFunctionCall;

use Drupal\Core\Config\ConfigFactoryInterface;
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
 * Plugin implementation of the getting field storage for one or all entities.
 */
#[FunctionCall(
  id: 'ai_agent:get_entity_type_field_storage',
  function_name: 'ai_agent_get_entity_type_field_storage',
  name: 'Get Field Storages',
  description: 'This methods gets all fields storage ids for an entity type or all entity types.',
  group: 'information_tools',
  context_definitions: [
    'entity_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Entity Type"),
      description: new TranslatableMarkup("The data name of the entity type you want to list the fields storages available for. Can be left empty for all."),
      required: FALSE,
    ),
  ],
)]
class GetEntityTypeFieldStorage extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

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
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

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
    $instance->configFactory = $container->get('config.factory');
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
    // Get the entity type if wanted.
    $entity_type = $this->getContextValue('entity_type');

    if (!$this->currentUser->hasPermission('administer ' . $entity_type . ' fields')) {
      throw new \Exception("You do not have permission to view field storage configs.");
    }

    // Get all the field storages.
    $fields = $this->entityTypeManager->getStorage('field_storage_config')->loadMultiple();
    $this->list = "Entity Type, Field Id\n";
    foreach ($fields as $field) {
      /** @var \Drupal\Core\Field\FieldStorageConfig $field */
      if (!$entity_type || ($entity_type && $field->getTargetEntityTypeId() == $entity_type)) {
        $this->list .= $field->getTargetEntityTypeId() . ", " . explode(".", $field->id())[1] . "\n";
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getReadableOutput(): string {
    return $this->list;
  }

}
