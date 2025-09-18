<?php

namespace Drupal\ai_agents\Plugin\AiFunctionCall;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai_agents\PluginInterfaces\AiAgentContextInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Plugin implementation of the list bundles function.
 */
#[FunctionCall(
  id: 'ai_agent:list_field_display_types',
  function_name: 'ai_agents_list_field_display_types',
  name: 'List Field Display Types',
  description: 'This method lists all field display types for a form view or a display view.',
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
    'field_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Field Type"),
      description: new TranslatableMarkup("If you just want the field display types for a specific field type, you can set it here."),
      required: FALSE,
    ),
  ],
)]
class ListFieldDisplayTypes extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

  /**
   * Get the current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

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
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
    $instance->currentUser = $container->get('current_user');
    $instance->fieldTypeManager = $container->get('plugin.manager.field.field_type');
    $instance->fieldWidgetManager = $container->get('plugin.manager.field.widget');
    $instance->fieldFormatterManager = $container->get('plugin.manager.field.formatter');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    // Load all the content entity types.
    $entity_types = $this->entityTypeManager->getDefinitions();
    $has_permission = FALSE;
    foreach ($entity_types as $entity_type_id => $entity_type) {
      // Check so its a content entity type and if the user has permissions.
      if ($entity_type->entityClassImplements(ContentEntityInterface::class) && $this->currentUser->hasPermission('administer ' . $entity_type_id . ' fields')) {
        // Its enough to have permission on one content entity type to be able
        // to list the field display types.
        $has_permission = TRUE;
        break;
      }
    }

    if (!$has_permission) {
      throw new \Exception('You do not have permission to create or edit field configs.');
    }

    // Get the context values.
    $type_of_display = $this->getContextValue('type_of_display');
    $field_type_id = $this->getContextValue('field_type');

    // Check if the type of display is valid.
    if (!in_array($type_of_display, ['form', 'display'], TRUE)) {
      $this->setOutput('The type of display is not valid. It should be either "form" or "display".');
      return;
    }

    // Check if the field type is valid.
    if ($field_type_id && !$this->fieldTypeManager->hasDefinition($field_type_id)) {
      $this->setOutput('The field type is not valid.');
      return;
    }
    $field_types_list = [];
    // Get the field types.
    if ($type_of_display === 'form') {
      $form_list = $this->fieldWidgetManager->getDefinitions();
      foreach ($form_list as $field_type => $definition) {
        if ($field_type_id && !in_array($field_type_id, $definition['field_types'])) {
          continue;
        }
        $field_types_list[$field_type] = [
          'label' => (string) $definition['label'],
          'field_types' => $definition['field_types'],
        ];
      }
    }
    elseif ($type_of_display === 'display') {
      $display_list = $this->fieldFormatterManager->getDefinitions();
      foreach ($display_list as $field_type => $definition) {
        if ($field_type_id && !in_array($field_type_id, $definition['field_types'])) {
          continue;
        }
        $field_types_list[$field_type] = [
          'label' => (string) $definition['label'],
          'field_types' => $definition['field_types'],
        ];
      }
    }
    $this->setOutput(Yaml::dump($field_types_list, 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
  }

}
