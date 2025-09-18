<?php

namespace Drupal\ai_agents\Plugin\AiFunctionCall;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Exception\AiToolsValidationException;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai_agents\PluginInterfaces\AiAgentContextInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Plugin implementation of the getting field config form function.
 */
#[FunctionCall(
  id: 'ai_agent:get_field_config_form',
  function_name: 'ai_agent_get_field_config_form',
  name: 'Get Field Config Form',
  description: 'This method gets the field config form, to check what values can be set on a field config. Either load with the field type or with the entity type, bundle and field name.',
  group: 'information_tools',
  context_definitions: [
    'entity_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Entity Type"),
      description: new TranslatableMarkup("The data name of the entity type you want to get a field config for."),
      required: FALSE,
    ),
    'bundle' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Bundle"),
      description: new TranslatableMarkup("The bundle of the entity type you want to get a field config for. If the entity type does not have bundles, you can set the entity type."),
      required: FALSE,
    ),
    'field_name' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Field Name"),
      description: new TranslatableMarkup("The field name of the field you want to get field config for."),
      required: FALSE,
    ),
    'field_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Field Type"),
      description: new TranslatableMarkup("The field type of the field you want to get field config form for."),
      required: FALSE,
    ),
  ],
)]
class GetFieldConfigForm extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

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
   * The field type plugin manager.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManagerInterface
   */
  protected $fieldTypeManager;

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
    $instance->fieldTypeManager = $container->get('plugin.manager.field.field_type');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    // Get the context.
    $field_type = $this->getContextValue('field_type');
    $entity_type = $this->getContextValue('entity_type');
    $bundle = $this->getContextValue('bundle');
    $field_name = $this->getContextValue('field_name');

    // The user need to be able to administer the field information.
    if ($entity_type && !$this->currentUser->hasPermission('administer ' . $entity_type . ' fields')) {
      throw new \Exception("You do not have permission to access this function.");
    }

    // If the field type is set, we check permissions against higher access.
    if ($field_type && !$entity_type && !$this->currentUser->hasPermission('administer site configuration')) {
      throw new \Exception('You do not have permission to access this function.');
    }

    // Get the field type plugin.
    if ($field_type && !$this->fieldTypeManager->hasDefinition($field_type)) {
      throw new AiToolsValidationException('This field type plugin does not exist.');
    }

    // If the field name is set, get the field definition.
    if (!$field_type) {
      // Check so all required context values are set.
      if (!$entity_type || !$bundle || !$field_name) {
        $this->setOutput('You need to set the entity, bundle and field name if you are not using field type.');
        return;
      }
      /** @var \Drupal\Core\Field\FieldDefinitionInterface $field_definition */
      $field_definition = $this->entityTypeManager->getStorage('field_config')->load($entity_type . '.' . $bundle . '.' . $field_name);
      if (!$field_definition) {
        // Get the field storage config.
        $field_storage = $this->entityTypeManager->getStorage('field_storage_config')->load($entity_type . '.' . $field_name);
        if (!$field_storage) {
          throw new AiToolsValidationException('This field storage config does not exist.');
        }
        // Create the field definition.
        $field_definition = $this->entityTypeManager->getStorage('field_config')->create([
          'field_storage' => $field_storage,
          'bundle' => 'article',
          'field_name' => 'test_field',
          'required' => FALSE,
          'settings' => [],
        ]);
      }
      $config = [
        'field_definition' => $field_definition,
        'name' => $field_name,
        'parent' => NULL,
      ];
      $field_type = $field_definition->getType();
    }
    else {
      // If the field name is not set, pseudo config.
      $storage = $this->entityTypeManager->getStorage('field_storage_config')->create([
        'type' => $field_type,
        'entity_type' => 'node',
        'field_name' => 'test_field',
      ]);
      $field_config = $this->entityTypeManager->getStorage('field_config')->create([
        'field_storage' => $storage,
        'bundle' => 'article',
        'entity_type' => 'node',
        'field_name' => 'test_field',
        'required' => FALSE,
        'settings' => [],
      ]);
      $config = [
        'field_definition' => $field_config,
        'name' => 'test_field',
        'parent' => NULL,
      ];
    }

    $element = [];

    // Special case for entity reference.
    if ($field_type === 'entity_reference') {
      $element = [
        'handler' => [
          'type' => 'string',
          'description' => 'The handler to use for the entity reference field using default:{entity_type}.',
        ],
        'handler_settings' => [
          'target_bundles' => [
            'type' => 'array',
            'description' => 'The target bundles for the entity reference field with the id as both key and value.',
          ],
          'auto_create' => [
            'type' => 'boolean',
            'description' => 'If the entity reference field should auto create entities. Only used for taxonomy terms.',
          ],
        ],
      ];
    }
    else {
      $instance = $this->fieldTypeManager->createInstance($field_type, $config);
      $form_state = new FormState();
      $element = $instance->fieldSettingsForm([], $form_state);
    }

    $this->setOutput("This is how you create a field config settings for $field_type:\n" . Yaml::dump($element, 10, 2));
  }

}
