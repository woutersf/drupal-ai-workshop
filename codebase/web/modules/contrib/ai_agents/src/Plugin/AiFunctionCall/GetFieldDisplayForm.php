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
  id: 'ai_agent:get_field_display_form',
  function_name: 'ai_agent_get_field_display_form',
  name: 'Get Field Display Form',
  description: 'This method gets the view or form display config form, to check what values can be set on it and the current values if it has.',
  group: 'information_tools',
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
      description: new TranslatableMarkup("The data name of the entity type you want to get a field view or form display form config for."),
      required: TRUE,
    ),
    'bundle' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Bundle"),
      description: new TranslatableMarkup("The bundle of the entity type you want to get a field view or form display form config for. If the entity type does not have bundles, you can set the entity type."),
      required: TRUE,
    ),
    'field_name' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Field Name"),
      description: new TranslatableMarkup("The field name of the field you want to get field view or form display form config for."),
      required: TRUE,
    ),
    'wanted_widget' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Wanted Widget"),
      description: new TranslatableMarkup("The view or form widget you want to use for the field. You only have to set this if you want to change the widget."),
      required: FALSE,
    ),
    'get_current_values' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup("Get Current Values"),
      description: new TranslatableMarkup("If you want to get the current values of the field view or form display form config."),
      required: FALSE,
    ),
    'get_full_display' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup("Get Full Display"),
      description: new TranslatableMarkup("If you want to get the full display of the field view or form display form config."),
      required: FALSE,
    ),
  ],
)]
class GetFieldDisplayForm extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

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
   * The field widget plugin manager.
   *
   * @var \Drupal\Core\Field\FieldWidgetPluginManagerInterface
   */
  protected $fieldWidgetManager;

  /**
   * The field formatter plugin manager.
   *
   * @var \Drupal\Core\Field\FieldFormatterPluginManagerInterface
   */
  protected $fieldFormatterManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

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
    $instance->fieldWidgetManager = $container->get('plugin.manager.field.widget');
    $instance->fieldFormatterManager = $container->get('plugin.manager.field.formatter');
    $instance->entityFieldManager = $container->get('entity_field.manager');
    $instance->entityDisplayRepository = $container->get('entity_display.repository');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    // Get the context.
    $entity_type = $this->getContextValue('entity_type');
    $bundle = $this->getContextValue('bundle');
    $field_name = $this->getContextValue('field_name');
    $type_of_display = $this->getContextValue('type_of_display');
    $wanted_widget = $this->getContextValue('wanted_widget');
    $get_current_values = $this->getContextValue('get_current_values');
    $get_full_display = $this->getContextValue('get_full_display');

    // The user need to be able to administer the field information.
    if (!$this->currentUser->hasPermission('administer ' . $entity_type . ' fields')) {
      throw new \Exception("You do not have permission to access this function.");
    }
    // Check if the entity type exists.
    if (!$this->entityTypeManager->hasDefinition($entity_type)) {
      throw new AiToolsValidationException("The entity type $entity_type does not exist.");
    }
    // Check if the field exists.
    $fields = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle);
    if (!isset($fields[$field_name])) {
      throw new AiToolsValidationException("The field $field_name does not exist on the entity type $entity_type.");
    }

    // Check what type of display it is.
    if ($type_of_display === 'form') {
      // If the wanted widget, doesn't exist, get the current one.
      if (!$wanted_widget) {
        $wanted_widget = $this->entityDisplayRepository->getFormDisplay($entity_type, $bundle)->getComponent($field_name)['type'];
      }
      // Check if the wanted widget exists.
      if (!$this->fieldWidgetManager->hasDefinition($wanted_widget)) {
        throw new AiToolsValidationException("The widget $wanted_widget does not exist.");
      }
      $config = [
        'field_definition' => $fields[$field_name],
        'settings' => $fields[$field_name]->getSettings(),
        'third_party_settings' => [],
      ];
      // Create an instance of the form display widget.
      $instance = $this->fieldWidgetManager->createInstance($wanted_widget, $config);
      $element['form_settings'] = $instance->settingsForm([], new FormState());

      // Check if we should get the current values.
      if ($get_full_display) {
        $display = $this->entityDisplayRepository->getFormDisplay($entity_type, $bundle);
        foreach ($display->getComponents() as $field_name => $component) {
          $element['full_display'][$field_name] = $component;
        }
      }
      elseif ($get_current_values) {
        $element['current_values'] = $this->entityDisplayRepository->getFormDisplay($entity_type, $bundle)->getComponent($field_name);
      }
    }
    else {
      // If the wanted widget, doesn't exist, get the current one.
      if (!$wanted_widget) {
        $wanted_widget = $this->entityDisplayRepository->getViewDisplay($entity_type, $bundle)->getComponent($field_name)['type'];
      }
      // Check if the wanted widget exists.
      if (!$this->fieldFormatterManager->hasDefinition($wanted_widget)) {
        throw new AiToolsValidationException("The formatter $wanted_widget does not exist.");
      }
      $config = [
        'field_definition' => $fields[$field_name],
        'settings' => $fields[$field_name]->getSettings(),
        'third_party_settings' => [],
        'view_mode' => 'full',
        'label' => 'default',
      ];
      // Create an instance of the view display widget.
      $instance = $this->fieldFormatterManager->createInstance($wanted_widget, $config);

      $element['view_settings'] = $instance->settingsForm([], new FormState());

      // Check if we should get the current values.
      if ($get_full_display) {
        $display = $this->entityDisplayRepository->getViewDisplay($entity_type, $bundle);
        foreach ($display->getComponents() as $field_name => $component) {
          $element['full_display'][$field_name] = $component;
        }
      }
      elseif ($get_current_values) {
        $element['current_values'] = $this->entityDisplayRepository->getViewDisplay($entity_type, $bundle)->getComponent($field_name);
      }
    }

    $this->setOutput(Yaml::dump($element, 10, 2));
  }

}
