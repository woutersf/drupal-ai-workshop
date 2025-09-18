<?php

namespace Drupal\ai_agents\Plugin\AiFunctionCall;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
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
  id: 'ai_agent:list_config_entities',
  function_name: 'list_config_entities',
  name: 'List Config Entities',
  description: 'This method can list config entities in any format wanted.',
  group: 'information_tools',
  context_definitions: [
    'entity_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Entity Type"),
      description: new TranslatableMarkup("The data name of the entity type you are trying to fetch entities for"),
      required: TRUE,
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
      description: new TranslatableMarkup("The fields to list. Leave empty to list all fields."),
      required: FALSE,
      default_value: ['id'],
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
class ListConfigEntities extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

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
    $instance->currentUser = $container->get('current_user');
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
    // Set the highest level of access check.
    if (!$this->currentUser->hasPermission('administer permissions')) {
      throw new \Exception('You do not have permission to list config entities.');
    }
    // Collect the context values.
    $entity_type = $this->getContextValue('entity_type');
    $amount = $this->getContextValue('amount');
    $offset = $this->getContextValue('offset');
    $fields = $this->getContextValue('fields');
    $sort_field = $this->getContextValue('sort_field');
    $sort_order = $this->getContextValue('sort_order');
    $storage = $this->entityTypeManager->getStorage($entity_type);
    if ($storage) {
      $query = $storage->getQuery();
      $query->accessCheck(TRUE);
      if ($sort_field && $sort_order) {
        $query->sort($sort_field, $sort_order);
      }
      if ($amount) {
        $query->range($offset, $amount);
      }
      $entity_ids = $query->execute();
      /** @var \Drupal\Core\Entity\ConfigEntityInterface[] $entities */
      $entities = $storage->loadMultiple($entity_ids);
      foreach ($entities as $entity) {
        // Make sure it really is a config entity.
        if (!$entity instanceof ConfigEntityInterface) {
          continue;
        }
        $entity_data = [];
        foreach ($fields as $field) {
          $entity_data[] = $entity->get($field);
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
