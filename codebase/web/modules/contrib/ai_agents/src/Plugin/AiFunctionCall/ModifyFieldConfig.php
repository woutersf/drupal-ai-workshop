<?php

namespace Drupal\ai_agents\Plugin\AiFunctionCall;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai_agents\PluginInterfaces\AiAgentContextInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Plugin implementation of the getting field config function.
 */
#[FunctionCall(
  id: 'ai_agent:manipulate_field_config',
  function_name: 'ai_agent_manipulate_field_config',
  name: 'Manipulate Field Config',
  description: 'This method creates or edits the field config.',
  group: 'modification_tools',
  context_definitions: [
    'type_of_operation' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Type of Operation"),
      description: new TranslatableMarkup("You have to specify if you want to edit or create the field config, so we can abort if its not correct."),
      required: TRUE,
      constraints: [
        'AllowedValues' => [
          'create',
          'edit',
        ],
      ],
    ),
    'entity_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Entity Type"),
      description: new TranslatableMarkup("The data name of the entity type you want to create field config for."),
      required: TRUE,
    ),
    'bundle' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Bundle"),
      description: new TranslatableMarkup("The bundle of the entity type you want to get a field config for. If the entity type does not have bundles, you can set the entity type."),
      required: TRUE,
    ),
    'field_name' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Field Name"),
      description: new TranslatableMarkup("The field name of the field to create a field config for."),
      required: TRUE,
    ),
    'field_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Field Type"),
      description: new TranslatableMarkup("The field type of the field to create a field config for."),
      required: TRUE,
    ),
    'label' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Label"),
      description: new TranslatableMarkup("The label of the field to create a field config for."),
      required: TRUE,
    ),
    'description' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Description"),
      description: new TranslatableMarkup("The description of the field to create a field config for."),
      required: FALSE,
    ),
    'required' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup("Required"),
      description: new TranslatableMarkup("Whether the field is required or not."),
      required: FALSE,
      default_value: FALSE,
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
      description: new TranslatableMarkup("The settings of the field to create a field config for. Should be an given as a json encoded string."),
      required: FALSE,
    ),
  ],
)]
class ModifyFieldConfig extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

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
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * The field type plugin manager.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManagerInterface
   */
  protected $fieldTypeManager;

  /**
   * The route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

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
    $instance->entityDisplayRepository = $container->get('entity_display.repository');
    $instance->fieldTypeManager = $container->get('plugin.manager.field.field_type');
    $instance->routeProvider = $container->get('router.route_provider');
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
    // Get the context.
    $type_of_operation = $this->getContextValue('type_of_operation');
    $field_name = $this->getContextValue('field_name');
    $entity_type = $this->getContextValue('entity_type');
    $bundle = $this->getContextValue('bundle');
    $label = $this->getContextValue('label');
    $description = $this->getContextValue('description');
    $required = $this->getContextValue('required');
    $translatable = $this->getContextValue('translatable');
    $settings = $this->getContextValue('settings');

    // The user have to have permission to administer fields on the entity type.
    if (!$this->currentUser->hasPermission('administer ' . $entity_type . ' fields')) {
      throw new \Exception("You do not have permission to create or edit field configs.");
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

    $config_name = $entity_type . '.' . $bundle . '.' . $field_name;
    try {
      /** @var \Drupal\field\FieldConfigInterface $field_config */
      $field_config = $this->entityTypeManager->getStorage('field_config')->load($config_name);
    }
    catch (\Exception $e) {
      $field_config = NULL;
    }

    // If the field config exists, return it.
    if ($field_config) {
      // If the operation is to create, return the field config.
      if ($type_of_operation === 'create') {
        $this->list = "The field config $config_name already exists. The following values is set on it.\n```yaml\n";
        $this->list .= Yaml::dump($field_config->toArray(), 10, 2);
        $this->list .= "```\n";
        return;
      }
    }
    else {
      if ($type_of_operation === 'edit') {
        $this->list = "The field config $config_name does not exist, so you can not edit it.";
        return;
      }
      // Create the field config.
      /** @var \Drupal\field\FieldConfigInterface $field_config */
      $field_config = $this->entityTypeManager->getStorage('field_config')->create([
        'id' => $config_name,
        'field_name' => $field_name,
        'entity_type' => $entity_type,
        'bundle' => $bundle,
      ]);
    }
    // Get the current settings and merge them with the new settings.
    $current_settings = $field_config->getSettings();
    if ($settings) {
      $settings = array_merge($current_settings, $settings);
    }
    // Edit the field config.
    $field_config->set('label', $label);
    $field_config->set('description', $description);
    $field_config->set('required', $required ?? FALSE);
    $field_config->set('translatable', $translatable ?? FALSE);
    $field_config->set('settings', $settings ?? []);
    $field_config->save();

    // If the type of operation is create, we also create form and display.
    if ($type_of_operation === 'create') {
      $form_display = $this->entityDisplayRepository->getFormDisplay($entity_type, $bundle, 'default');
      $view_display = $this->entityDisplayRepository->getViewDisplay($entity_type, $bundle, 'default');

      // Get the default field display and form display.
      $field_info = $this->fieldTypeManager->getDefinition($field_config->getType());
      $form_display_default = $field_info['default_widget'];
      $view_display_default = $field_info['default_formatter'];
      $form_display->setComponent($field_name, [
        'type' => $form_display_default,
        'weight' => 50,
      ]);
      $form_display->save();
      $view_display->setComponent($field_name, [
        'type' => $view_display_default,
        'weight' => 50,
      ]);
      $view_display->save();
    }

    $route = 'entity.field_config.' . $entity_type . '_field_edit_form';
    $arguments = $this->getRouteArguments($route, $entity_type, $bundle, [
      'field_config' => $field_config->id(),
    ]);

    $link = Url::fromRoute($route, $arguments)->toString();
    $this->list = "The $type_of_operation of the field config $config_name has been finished. The field can be under $link. It looks like this.\n```yaml\n";
    $this->list .= Yaml::dump($field_config->toArray(), 10, 2);
    $this->list .= "```\n";
  }

  /**
   * {@inheritdoc}
   */
  public function getReadableOutput(): string {
    return $this->list;
  }

  /**
   * Get a route arguments for a bundle.
   *
   * @param string $route
   *   The route.
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   * @param array $extraArguments
   *   Extra arguments to fill in.
   *
   * @return array
   *   The arguments filled out
   */
  public function getRouteArguments($route, $entity_type, $bundle, $extraArguments = []) {
    $arguments = [
      'entity_type' => $entity_type,
    ];
    $routeData = $this->routeProvider->getRouteByName($route);
    $parameters = $routeData->compile()->getPathVariables();
    if (isset($parameters[0])) {
      $arguments[$parameters[0]] = $bundle;
    }
    // Merge the extra arguments.
    $arguments = array_merge($arguments, $extraArguments);
    return $arguments;
  }

}
