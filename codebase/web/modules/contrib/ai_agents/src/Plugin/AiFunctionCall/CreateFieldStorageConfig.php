<?php

namespace Drupal\ai_agents\Plugin\AiFunctionCall;

use Drupal\Component\Serialization\Json;
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
  id: 'ai_agent:create_field_storage_config',
  function_name: 'ai_agent_create_field_storage_config',
  name: 'Create Field Storage Config',
  description: 'This method creates the field storage config.',
  group: 'modification_tools',
  context_definitions: [
    'entity_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Entity Type"),
      description: new TranslatableMarkup("The data name of the entity type you want to create field storage config for."),
      required: TRUE,
    ),
    'field_name' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Field Name"),
      description: new TranslatableMarkup("The field name of the field to create a field storage config for."),
      required: TRUE,
    ),
    'field_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Field Type"),
      description: new TranslatableMarkup("The field type of the field to create a field storage config for."),
      required: TRUE,
    ),
    'cardinality' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Cardinality"),
      description: new TranslatableMarkup("The cardinality of the field to create a field storage config for. -1 means unlimited, 1 means single value, and any other number means that many values."),
      required: FALSE,
      default_value: "1",
    ),
    'translatable' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup("Translatable"),
      description: new TranslatableMarkup("Whether the field is translatable or not."),
      required: FALSE,
      default_value: FALSE,
    ),
    'settings' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Settings"),
      description: new TranslatableMarkup("The settings of the field to create a field storage config for. Should be an given as a json encoded string."),
      required: FALSE,
    ),
  ],
)]
class CreateFieldStorageConfig extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

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
    $field_type = $this->getContextValue('field_type');
    $cardinality = $this->getContextValue('cardinality');
    $translatable = $this->getContextValue('translatable');
    $settings = $this->getContextValue('settings');

    if (!$this->currentUser->hasPermission('administer ' . $entity_type . ' fields')) {
      throw new \Exception("You do not have permission to create field storage configs.");
    }

    // Try to decode the settings.
    if ($settings) {
      $settings = Json::decode($settings);
      if (json_last_error() !== JSON_ERROR_NONE) {
        $this->list = "The settings could not be decoded. Please make sure it is a valid JSON string.";
        return;
      }
    }

    // Check so the entity exists.
    if (!$this->entityTypeManager->getDefinition($entity_type)) {
      $this->list = "The entity type $entity_type does not exist.";
      return;
    }

    $config_name = $entity_type . '.' . $field_name;
    try {
      /** @var \Drupal\field\FieldStorageConfigInterface $storage_config */
      $storage_config = $this->entityTypeManager->getStorage('field_storage_config')->load($config_name);
    }
    catch (\Exception $e) {
      $storage_config = NULL;
    }
    // If the storage_config exists, return it.
    if ($storage_config) {
      // Check if the field type is the same.
      if ($storage_config->getType() === $field_type) {
        $this->list = "The field storage config already exists, but you can reuse it.";
        return;
      }
      else {
        $this->list = "The field storage config exists but the field type is different, so you will not be able to use this field name.";
        return;
      }
    }
    // Create the field storage config.
    $storage_config = $this->entityTypeManager->getStorage('field_storage_config')->create([
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'type' => $field_type,
      'cardinality' => $cardinality,
      'translatable' => $translatable,
      'settings' => $settings ?? [],
    ]);
    $storage_config->save();
    $this->list = "The field storage config has been created like this.\n```yaml\n";
    $this->list .= Yaml::dump($storage_config->toArray(), 10, 2);
    $this->list .= "```\n";
  }

  /**
   * {@inheritdoc}
   */
  public function getReadableOutput(): string {
    return $this->list;
  }

}
