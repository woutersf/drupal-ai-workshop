<?php

namespace Drupal\ai_agents\Plugin\AiFunctionCall;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
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
 * Plugin implementation of the get field information for an entity/bundle type.
 */
#[FunctionCall(
  id: 'ai_agent:get_entity_field_information',
  function_name: 'ai_agents_get_entity_field_information',
  name: 'Get Entity Field Information',
  description: 'This method can get field information or all the fields information for an entity and bundle type, to know what fields exists and what their configurations are. If entity type or bundle is omitted it will give back for all.',
  group: 'information_tools',
  context_definitions: [
    'entity_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Entity Type"),
      description: new TranslatableMarkup("The data name of the entity type you want to get values for."),
      required: TRUE,
    ),
    'bundle' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Bundle"),
      description: new TranslatableMarkup("The data name of the bundle type you want to get values for."),
      required: FALSE,
    ),
    'field_name' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Field Name"),
      description: new TranslatableMarkup("The data name of the field you want to get values for."),
      required: FALSE,
    ),
  ],
)]
class GetEntityFieldInformation extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

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
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
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
    $instance->entityFieldManager = $container->get('entity_field.manager');
    $instance->bundleInfoService = $container->get('entity_type.bundle.info');
    $instance->currentUser = $container->get('current_user');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    // Collect the context values.
    $entity_type = $this->getContextValue('entity_type');
    $bundle = $this->getContextValue('bundle');
    $field_name_selected = $this->getContextValue('field_name');
    // The user need to be able to administer the field information.
    if (!$this->currentUser->hasPermission('administer ' . $entity_type . ' fields')) {
      throw new \Exception("You do not have permission to access this function.");
    }

    // Load field configurations for the entity type and bundle.
    $bundles = [];
    if ($entity_type && $bundle) {
      // Verify that the entity type and bundle exists.
      $possible_bundles = $this->bundleInfoService->getBundleInfo($entity_type);
      if (!isset($possible_bundles[$bundle])) {
        $this->setOutput("Entity or Bundle not found.");
        return;
      }
      $bundles[$entity_type][] = $bundle;
    }
    elseif ($entity_type) {
      $possible_bundles = $this->bundleInfoService->getBundleInfo($entity_type);
      foreach ($possible_bundles as $bundle_type => $bundle_info) {
        $bundles[$entity_type][] = $bundle_type;
      }
    }
    else {
      // Get all entity types.
      $entity_types = $this->entityTypeManager->getDefinitions();
      foreach ($entity_types as $entity_type => $entity_type_definition) {
        // Only content entities have bundles.
        if ($entity_type_definition instanceof ContentEntityTypeInterface) {
          $possible_bundles = $this->bundleInfoService->getBundleInfo($entity_type);
          foreach ($possible_bundles as $bundle_type => $bundle_info) {
            $bundles[$entity_type][] = $bundle_type;
          }
        }
      }
    }

    // Now we have all the bundles, get the field information.
    $field_information = [];
    foreach ($bundles as $entity_type => $bundle_types) {
      foreach ($bundle_types as $bundle_type) {
        $fields = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle_type);
        foreach ($fields as $field_name => $field_definition) {
          // If field_name is set, only show that field.
          if ($field_name_selected && $field_name_selected !== $field_name) {
            continue;
          }
          // Only show configured fields.
          if ($field_definition->isComputed()) {
            continue;
          }
          // Get info about field_type, cardinality, etc.
          $information['field_name'] = $field_definition->getLabel();
          $information['field_id'] = $field_name;
          $information['read_only'] = $field_definition->isReadOnly();
          $information['entity_type'] = $entity_type;
          $information['bundle_type'] = $bundle_type;
          $information['field_name'] = $field_name;
          $information['field_type'] = $field_definition->getType();
          $information['cardinality'] = $field_definition->getFieldStorageDefinition()->getCardinality();
          $information['required'] = $field_definition->isRequired();
          $information['translatable'] = $field_definition->isTranslatable();
          // We also give back extra info for entity reference fields.
          if (in_array($field_definition->getType(), [
            'entity_reference',
          ])) {
            $information['target_entity_type'] = $field_definition->getSetting('target_type');
            $information['target_bundle_type'] = $field_definition->getSetting('handler_settings')['target_bundles'] ?? [];
          }

          $field_information[$entity_type][$bundle_type][$field_name] = $information;
        }
      }
    }

    $this->setOutput(Yaml::dump($field_information, 10, 2));
  }

}
