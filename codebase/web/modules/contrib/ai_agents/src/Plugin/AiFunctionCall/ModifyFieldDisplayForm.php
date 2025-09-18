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

/**
 * Plugin implementation of the getting field config function.
 */
#[FunctionCall(
  id: 'ai_agent:manipulate_field_display_form',
  function_name: 'ai_agent_manipulate_field_display_form',
  name: 'Manipulate Field Display Form',
  description: 'This method creates or edits the field views or form display components.',
  group: 'modification_tools',
  context_definitions: [
    'type_of_display' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Type of Display"),
      description: new TranslatableMarkup("You have to specify if you want to list the field display types for a form view or a display view."),
      required: TRUE,
      constraints: [
        'AllowedValues' => [
          'form',
          'display',
        ],
      ],
    ),
    'entity_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Entity Type"),
      description: new TranslatableMarkup("The data name of the entity type you want to create field display for."),
      required: TRUE,
    ),
    'bundle' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Bundle"),
      description: new TranslatableMarkup("The bundle of the entity type you want to get a field display for. If the entity type does not have bundles, you can set the entity type."),
      required: TRUE,
    ),
    'field_name' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Field Name"),
      description: new TranslatableMarkup("The field name of the field to create a field display for."),
      required: TRUE,
    ),
    'label' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Label"),
      description: new TranslatableMarkup("If the label should be hidden, above etc. Only set this if you want to change the label and if its a view display."),
      required: FALSE,
    ),
    'type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Widget Type"),
      description: new TranslatableMarkup("The widget type of the field to manipulate a display for. Only set this if you want to change the widget type."),
      required: TRUE,
    ),
    'weight' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup("Weight"),
      description: new TranslatableMarkup("The weight of the field to manipulate a display for. Only set this if you want to change the weight."),
      required: FALSE,
      default_value: 0,
    ),
    'settings' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Settings"),
      description: new TranslatableMarkup("The settings of the field to manipulate a field widget for. Should be an given as a json encoded string."),
      required: FALSE,
    ),
  ],
)]
class ModifyFieldDisplayForm extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

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
   * {@inheritdoc}
   */
  public function execute() {
    // Get the context.
    $type_of_display = $this->getContextValue('type_of_display');
    $entity_type = $this->getContextValue('entity_type');
    $bundle = $this->getContextValue('bundle');
    $field_name = $this->getContextValue('field_name');
    $label = $this->getContextValue('label');
    $type_of_operation = $this->getContextValue('type');
    $weight = $this->getContextValue('weight');
    $settings = $this->getContextValue('settings');

    $form_prepend = $type_of_display == 'form' ? 'form display' : 'display';

    // Check if the user can create or edit the field config.
    if (!$this->currentUser->hasPermission("administer $entity_type $form_prepend")) {
      throw new \Exception('You do not have permission to create or edit ' . $form_prepend . ' for ' . $entity_type . '.');
    }

    // Try to decode the settings.
    if ($settings) {
      $settings = Json::decode($settings);
      if (json_last_error() !== JSON_ERROR_NONE) {
        $this->setOutput('The settings are not valid JSON.');
        return;
      }
    }

    // Check so the entity exists.
    if (!$this->entityTypeManager->getDefinition($entity_type)) {
      $this->setOutput('The entity type does not exist.');
      return;
    }

    $config_name = $entity_type . '.' . $bundle . '.' . $field_name;
    /** @var \Drupal\field\FieldConfigInterface $field_config */
    $field_config = $this->entityTypeManager->getStorage('field_config')->load($config_name);

    if ($type_of_display === 'form') {
      $form_display = $this->entityDisplayRepository->getFormDisplay($entity_type, $bundle, 'default');
      // Get the current component.
      $component = $form_display->getComponent($field_name);
      // Make sure the component exists.
      if (!$component) {
        $this->setOutput('The form display for ' . $config_name . ' does not exist.');
        return;
      }
      // Set the new component.
      if (is_numeric($weight)) {
        $component['weight'] = $weight;
      }
      if ($label) {
        $component['label'] = $label;
      }
      // Get the current settings.
      $current_settings = $component['settings'];
      // Merge the new settings with the current settings.
      if (is_array($settings)) {
        $current_settings = array_merge($current_settings, $settings);
      }
      $component['settings'] = $current_settings;
      if ($type_of_operation) {
        $component['type'] = $type_of_operation;
      }
      $form_display->setComponent($field_name, $component);
      $form_display->save();
      $route = 'entity.entity_form_display.' . $entity_type . '.default';
      $arguments = $this->getRouteArguments($route, $entity_type, $bundle, [
        'form_mode' => 'default',
      ]);

      $link = Url::fromRoute($route, $arguments)->toString();
      $this->setOutput('The form display for ' . $config_name . ' has been updated. You can view it here: ' . $link);
    }
    else {
      $display = $this->entityDisplayRepository->getViewDisplay($entity_type, $bundle, 'default');
      // Get the current component.
      $component = $display->getComponent($field_name);
      // Make sure the component exists.
      if (!$component) {
        $this->setOutput('The display for ' . $config_name . ' does not exist.');
        return;
      }
      // Set the new component.
      if (is_numeric($weight)) {
        $component['weight'] = $weight;
      }
      if ($label) {
        $component['label'] = $label;
      }
      // Get the current settings.
      $current_settings = $component['settings'];
      // Merge the new settings with the current settings.
      if (is_array($settings)) {
        $current_settings = array_merge($current_settings, $settings);
      }
      $component['settings'] = $current_settings;
      if ($type_of_operation) {
        $component['type'] = $type_of_operation;
      }
      $display->setComponent($field_name, $component);
      $display->save();
      $route = 'entity.entity_view_display.' . $entity_type . '.default';
      $arguments = $this->getRouteArguments($route, $entity_type, $bundle, [
        'view_mode' => 'default',
        'field_name' => $field_name,
      ]);

      $link = Url::fromRoute($route, $arguments)->toString();
      $this->setOutput('The display for ' . $config_name . ' has been updated. You can view it here: ' . $link);
    }
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
