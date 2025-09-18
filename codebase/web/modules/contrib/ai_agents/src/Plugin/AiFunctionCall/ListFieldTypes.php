<?php

namespace Drupal\ai_agents\Plugin\AiFunctionCall;

use Drupal\Component\Serialization\Json;
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
  id: 'ai_agent:list_field_types',
  function_name: 'ai_agents_list_field_types',
  name: 'List Field Types',
  description: 'This method list all field types available on the website.',
  group: 'information_tools',
  context_definitions: [
    'field_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Field Type"),
      description: new TranslatableMarkup("If you just want information about one field type."),
      required: FALSE,
    ),
    'simple_representation' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup("Simple Representation"),
      description: new TranslatableMarkup("If you want a simple representation of the field type."),
      required: FALSE,
    ),
  ],
)]
class ListFieldTypes extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

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
   * The entity type manager.
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

    // Get the field type from the context.
    $field_type_id = $this->getContextValue('field_type');
    $simple_representation = $this->getContextValue('simple_representation');

    // Get all field types.
    $field_types = $this->fieldTypeManager->getDefinitions();
    $field_types_list = [];
    foreach ($field_types as $id => $field_type) {
      if ($field_type_id && $field_type_id !== $id) {
        continue;
      }
      if ($simple_representation) {
        $field_types_list[$id]['id'] = (string) $id;
        $field_types_list[$id]['label'] = (string) $field_type['label'];
        $field_types_list[$id]['description'] = isset($field_type['description']) && is_string($field_type['description']) ? (string) $field_type['description'] : '';
        continue;
      }
      foreach ($field_type as $key => $value) {
        $field_types_list[$id][$key] = (is_array($value) || is_object($value)) && !$value instanceof TranslatableMarkup ? Json::encode($value) : (string) $value;
      }
    }
    $this->setOutput(Yaml::dump($field_types_list, 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
  }

}
