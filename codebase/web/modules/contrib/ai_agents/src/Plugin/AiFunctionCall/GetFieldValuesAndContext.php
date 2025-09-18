<?php

namespace Drupal\ai_agents\Plugin\AiFunctionCall;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\OptionsProviderInterface;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai_agents\PluginInterfaces\AiAgentContextInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Plugin implementation of the get field data values that needs to be inserted.
 */
#[FunctionCall(
  id: 'ai_agent:get_field_values_and_context',
  function_name: 'ai_agents_get_field_values_and_context',
  name: 'Get Field Values and Context',
  description: 'This method gets information about how you need to fill in a specific field for and entity type (and possibly bundle).',
  group: 'information_tools',
  context_definitions: [
    'entity_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Entity Type"),
      description: new TranslatableMarkup("The data name of the entity type to get the field value information for."),
      required: TRUE,
    ),
    'bundle' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Bundle"),
      description: new TranslatableMarkup("The data name of the bundle type to get the field value information for."),
      required: TRUE,
    ),
    'field_name' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Field Name"),
      description: new TranslatableMarkup("The data name of the bundle type to get the field value information for."),
      required: TRUE,
    ),
  ],
)]
class GetFieldValuesAndContext extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

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
   * The bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected EntityTypeBundleInfoInterface $bundleInfoService;

  /**
   * The typed data manager.
   *
   * @var \Drupal\Core\TypedData\TypedDataManagerInterface
   */
  protected TypedDataManagerInterface $typedDataManager;

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
    $instance->bundleInfoService = $container->get('entity_type.bundle.info');
    $instance->typedDataManager = $container->get('typed_data_manager');
    $instance->currentUser = $container->get('current_user');
    return $instance;
  }

  /**
   * The information.
   *
   * @var string
   */
  protected string $fieldInformation = "";

  /**
   * {@inheritdoc}
   */
  public function execute() {
    // Collect the context values.
    $entity_type = $this->getContextValue('entity_type');
    $bundle = $this->getContextValue('bundle');
    $field_name = $this->getContextValue('field_name');

    // The user need to be able to administer the field information.
    if (!$this->currentUser->hasPermission('administer ' . $entity_type . ' fields')) {
      throw new \Exception("You do not have permission to access this tool.");
    }

    $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle);
    $field_definition = $field_definitions[$field_name];
    $field_storage_definition = $field_definition->getFieldStorageDefinition();
    // Get the field type object.
    $field_type_object = $field_definition->getItemDefinition()->getClass();
    // Get the property definitions.
    $property_definitions = $field_type_object::propertyDefinitions($field_storage_definition);

    $field_information = [
      'field_name' => $field_definition->getName(),
      'field_type' => $field_definition->getType(),
      'default_value' => $field_definition->getDefaultValueLiteral(),
    ];
    $field_information['target_entity_type'] = $field_definition->getSetting('target_type');
    $field_information['target_bundle_type'] = $field_definition->getSetting('handler_settings')['target_bundles'] ?? [];
    $field_information['values_to_fill'] = [];
    foreach ($property_definitions as $property_name => $property_definition) {
      // Get the actual data definition.
      $data_definition = $this->typedDataManager->createInstance($property_definition->getDataType(), [
        'data_definition' => $property_definition,
        'name' => $property_name,
        'parent' => NULL,
      ]);

      $field_information['values_to_fill'][$property_name] = [
        'type' => $property_definition->getDataType(),
        'required' => $property_definition->isRequired(),
        'label' => $property_definition->getLabel(),
        'description' => $property_definition->getDescription(),
        'settings' => $property_definition->getSettings(),
        'is_list' => $property_definition->isList(),
        'options' => $data_definition instanceof OptionsProviderInterface ? $data_definition->getSettableOptions() : NULL,
      ];
    }
    $this->fieldInformation = Yaml::dump($field_information, 2, 10);
  }

  /**
   * {@inheritdoc}
   */
  public function getReadableOutput(): string {
    return $this->fieldInformation;
  }

}
