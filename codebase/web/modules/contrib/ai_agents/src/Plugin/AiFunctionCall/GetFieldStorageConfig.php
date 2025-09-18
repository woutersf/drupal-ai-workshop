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
use Symfony\Component\Yaml\Yaml;

/**
 * Plugin implementation of the getting field storage config function.
 */
#[FunctionCall(
  id: 'ai_agent:get_field_storage',
  function_name: 'ai_agent_get_field_storage',
  name: 'Get Field Storage Config',
  description: 'This method gets the field storage config.',
  group: 'information_tools',
  context_definitions: [
    'entity_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Entity Type"),
      description: new TranslatableMarkup("The data name of the entity type you want to get a field storage config for."),
      required: TRUE,
    ),
    'field_name' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Field Name"),
      description: new TranslatableMarkup("The field name of the field you want to get field storage config for."),
      required: TRUE,
    ),
  ],
)]
class GetFieldStorageConfig extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

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
    // Get the entity id.
    $field_name = $this->getContextValue('field_name');
    $entity_type = $this->getContextValue('entity_type');

    $config_name = $entity_type . '.' . $field_name;
    try {
      $entity = $this->entityTypeManager->getStorage('field_storage_config')->load($config_name);
    }
    catch (\Exception $e) {
      // Could not load the entity.
      $this->list = "Could not load the field storage config.";
      return;
    }
    if (!$entity) {
      // The entity does not exist.
      $this->list = "The field storage config does not exist.";
      return;
    }
    // Check access.
    if (!$entity->access('view')) {
      // The user does not have access to the entity.
      throw new \Exception("You do not have permission to access this field configuration.");
    }
    // Yaml encode the entity.
    $this->list = Yaml::dump($entity->toArray(), 10, 2);
  }

  /**
   * {@inheritdoc}
   */
  public function getReadableOutput(): string {
    return $this->list;
  }

}
