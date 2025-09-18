<?php

namespace Drupal\ai_agents\Plugin\AiFunctionCall;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
 * Plugin implementation of the get current values of some entity.
 */
#[FunctionCall(
  id: 'ai_agent:get_current_content_entity_values',
  function_name: 'ai_agents_get_current_content_entity_values',
  name: 'Get Current Content Entity Values',
  description: 'This method will get current content entity values from a specific entity.',
  group: 'information_tools',
  context_definitions: [
    'entity_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Entity Type"),
      description: new TranslatableMarkup("The data name of the entity type you want to get values for."),
      required: TRUE,
    ),
    'entity_id' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup("Entity Id"),
      description: new TranslatableMarkup("The id of the entity you want to get values for."),
      required: TRUE,
    ),
    'field_names' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup("Field Names"),
      description: new TranslatableMarkup("The field you want to get value for, can be left empty to get all."),
      required: FALSE,
    ),
  ],
)]
class GetCurrentContentEntityValues extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

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
    $entity_id = $this->getContextValue('entity_id');
    $field_names = $this->getContextValue('field_names');

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
    // Check if the entity exists.
    if (!$entity) {
      $this->fieldInformation = 'Entity not found.';
      return;
    }
    // Check so its a content entity.
    if (!($entity instanceof ContentEntityInterface)) {
      $this->fieldInformation = 'Not a content entity.';
      return;
    }
    // Check so the user has access.
    if (!$entity->access('view')) {
      throw new \Exception('Access denied to the entity.');
    }
    // Get an array version of the entity.
    $field_information = [];
    if (!empty($field_names)) {
      foreach ($field_names as $field_name) {
        $field_information[$field_name] = $entity->get($field_name)->getValue();
      }
    }
    else {
      foreach ($entity->getFields() as $field_name => $field) {
        $field_information[$field_name] = $field->getValue();
      }
    }
    $this->fieldInformation = Yaml::dump($field_information);
  }

  /**
   * {@inheritdoc}
   */
  public function getReadableOutput(): string {
    return $this->fieldInformation;
  }

}
