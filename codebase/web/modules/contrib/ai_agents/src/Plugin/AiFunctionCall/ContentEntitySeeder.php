<?php

namespace Drupal\ai_agents\Plugin\AiFunctionCall;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai_agents\Plugin\AiFunctionCall\Children\ContentEntityFieldValuesList;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation the tool to edit or create entities.
 */
#[FunctionCall(
  id: 'ai_agent:content_entity_seeder',
  function_name: 'ai_agents_content_entity_seeder',
  name: 'Content Entity Seeder',
  description: 'This method can be used to create or edit and entity from an entity array.',
  group: 'modification_tools',
  context_definitions: [
    'entity_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Entity Type"),
      description: new TranslatableMarkup("The entity type you want to create or edit."),
      required: TRUE
    ),
    'entity_id' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup("Entity Id"),
      description: new TranslatableMarkup("The entity id if its an edit action. Do not set it if you are creating a new entity."),
      required: FALSE,
    ),
    'label' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Label"),
      description: new TranslatableMarkup("If you are creating or changing the label/title, this is where that gets set."),
      required: FALSE,
    ),
    'bundle' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Bundle"),
      description: new TranslatableMarkup("If you are creating you will have to set a bundle. For updating you always leave this empty."),
      required: FALSE,
    ),
    'entity_array' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup("Entity Array"),
      description: new TranslatableMarkup("The entity array to seed the entity with."),
      required: TRUE,
      multiple: TRUE,
      constraints: [
        'ComplexToolItems' => ContentEntityFieldValuesList::class,
      ],
    ),
  ],
)]
class ContentEntitySeeder extends FunctionCallBase implements ExecutableFunctionCallInterface {

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
    $entity_id = $this->getContextValue('entity_id');
    $label = $this->getContextValue('label');
    $entity_array = $this->getContextValue('entity_array');

    // First check so the entity storage exists.
    /** @var \Drupal\Core\Entity\EntityStorageInterface $entityStorage */
    $entity_storage = $this->entityTypeManager->getStorage($entity_type);
    if (!$entity_storage) {
      $this->fieldInformation = 'The entity type does not exist.';
      return;
    }
    // If its edit we have to check that it exists, otherwise create one.
    $type_of_change = 'edited';
    if ($entity_id) {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $entity = $entity_storage->load($entity_id);
      if (!$entity) {
        $this->fieldInformation = 'The entity does not exist.';
        return;
      }
      // Make sure that the user has access to edit.
      if (!$entity->access('update')) {
        $this->fieldInformation = 'Access denied.';
        return;
      }
    }
    else {
      $type_of_change = 'created';
      $bundle_label = $entity_storage->getEntityType()->getKey('bundle');
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $entity = $entity_storage->create([
        $bundle_label => $bundle,
      ]);
      // Make sure that the user has access to create.
      if (!$entity->access('create')) {
        $this->fieldInformation = 'Access denied.';
        return;
      }
    }
    // Loop through the entity array and set the values.
    $data = [];
    foreach ($entity_array as $lists) {
      $field_name = $lists['field_name'];
      $values = [];
      foreach ($lists['field_values'] as $field_value) {
        $values[$field_value['value_name']] = $field_value['values'][0];
      }
      $data[$field_name][] = $values;
    }

    // Get the label name for the entity type.
    $label_name = $entity->getEntityType()->getKey('label');
    if ($label) {
      $data[$label_name] = $label;
    }

    foreach ($data as $field_name => $value) {
      $entity->set($field_name, $value);
    }
    $entity->save();
    // Get the view link information and edit link information for the entity.
    $view_link = $entity->toUrl()->toString();
    $edit_link = $entity->toUrl('edit-form')->toString();
    $this->fieldInformation = "Entity of type $entity_type $type_of_change with id: " . $entity->id() . " View link: $view_link Edit link: $edit_link";
  }

  /**
   * {@inheritdoc}
   */
  public function getReadableOutput(): string {
    return $this->fieldInformation;
  }

}
