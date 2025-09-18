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
 * Plugin implementation of the list entities function.
 */
#[FunctionCall(
  id: 'ai_agent:list_content_entities',
  function_name: 'list_content_entities',
  name: 'List Content Entities',
  description: 'This method can list content entities in any format wanted.',
  group: 'information_tools',
  context_definitions: [
    'entity_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Entity Type"),
      description: new TranslatableMarkup("The data name of the entity type you are trying to fetch entities for"),
      required: TRUE,
    ),
    'bundle' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Bundle"),
      description: new TranslatableMarkup("The data name of the bundle type you want to get values for."),
      required: FALSE,
    ),
    'entity_id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Entity ID"),
      description: new TranslatableMarkup("The entity id to load an entity for."),
      required: FALSE,
    ),
    'amount' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup("Amount"),
      description: new TranslatableMarkup("The amount of entities to fetch. 0 means all."),
      required: FALSE,
      default_value: 10,
    ),
    'offset' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup("Offset"),
      description: new TranslatableMarkup("The offset of entities to fetch."),
      required: FALSE,
      default_value: 0,
    ),
    'fields' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup("Fields"),
      description: new TranslatableMarkup("The fields to list."),
      required: FALSE,
    ),
    'sort_field' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Sort Field"),
      description: new TranslatableMarkup("The field to sort by."),
      required: FALSE,
    ),
    'sort_order' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Sort Order"),
      description: new TranslatableMarkup("The sort order."),
      required: FALSE,
      default_value: 'DESC',
    ),
  ],
)]
class ListContentEntities extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

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
   * The list.
   *
   * @var array
   */
  protected array $list = [];

  /**
   * {@inheritdoc}
   */
  public function execute() {
    // Collect the context values.
    $entity_type = $this->getContextValue('entity_type');
    $bundle = $this->getContextValue('bundle');
    $entity_id = $this->getContextValue('entity_id');
    $amount = $this->getContextValue('amount');
    $offset = $this->getContextValue('offset');
    $fields = $this->getContextValue('fields');
    $sort_field = $this->getContextValue('sort_field');
    $sort_order = $this->getContextValue('sort_order');
    $storage = $this->entityTypeManager->getStorage($entity_type);

    // We need to know the base fields of the entity.
    $entity_type_info = $this->entityTypeManager->getDefinition($entity_type);
    $bundle_key = $entity_type_info->getKey('bundle');

    if ($storage) {
      $query = $storage->getQuery();
      // Only allow access to entities that the user has access to.
      $query->accessCheck(TRUE);
      if ($sort_field && $sort_order) {
        $query->sort($sort_field, $sort_order);
      }
      if ($bundle && $bundle_key) {
        $query->condition($bundle_key, $bundle);
      }
      if ($amount) {
        $query->range($offset, $amount);
      }
      if ($entity_id) {
        $query->condition($entity_type_info->getKey('id'), (int) $entity_id);
      }
      $entity_ids = $query->execute();
      /** @var \Drupal\Core\Entity\ContentEntityInterface[] $entities */
      $entities = $storage->loadMultiple($entity_ids);
      foreach ($entities as $entity) {
        // Make sure the user has access to view it.
        if (!$entity->access('view')) {
          continue;
        }
        // Make sure that the entity is a content entity.
        if (!$entity instanceof ContentEntityInterface) {
          continue;
        }
        $entity_data = [];
        if (!is_null($fields) && count($fields)) {
          foreach ($fields as $field) {
            $entity_data[$field] = $entity->get($field)->getValue();
          }
        }
        else {
          // Else the whole entity.
          $entity_data[] = $entity->toArray();
        }
        $this->list[] = $entity_data;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getReadableOutput(): string {
    $output = Yaml::dump($this->list, 10, 2);
    return $output;
  }

}
