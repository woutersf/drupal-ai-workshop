<?php

namespace Drupal\ai_agents\Plugin\AiFunctionCall;

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

/**
 * Plugin implementation of the list bundles function.
 */
#[FunctionCall(
  id: 'ai_agent:list_bundles',
  function_name: 'list_bundles',
  name: 'List Bundles',
  description: 'This method can list bundles for an entity type.',
  group: 'information_tools',
  context_definitions: [
    'entity_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Entity Type"),
      description: new TranslatableMarkup("The data name of the entity type you want to get values for."),
      required: FALSE,
    ),
  ],
)]
class ListBundles extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

  /**
   * The bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected EntityTypeBundleInfoInterface $bundleInfoService;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The current user.
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
    $instance->bundleInfoService = $container->get('entity_type.bundle.info');
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
    // Check against the highest permission level.
    if (!$this->currentUser->hasPermission('administer permissions')) {
      throw new \Exception('You do not have permission to access this tool.');
    }
    // Collect the context values.
    $entity_type = $this->getContextValue('entity_type');

    if (!$entity_type) {
      // Get all entity types.
      $entity_types = $this->entityTypeManager->getDefinitions();

      foreach ($entity_types as $type => $label) {
        $this->list[$type] = $this->bundleInfoService->getBundleInfo($type);
      }
      return;
    }
    $this->list[$entity_type] = $this->bundleInfoService->getBundleInfo($entity_type);
  }

  /**
   * {@inheritdoc}
   */
  public function getReadableOutput(): string {
    $output = 'Entity Type, Bundle, Readable Name' . PHP_EOL;
    foreach ($this->list as $entity_type => $fields) {
      foreach ($fields as $bundle => $bundle_info) {
        $output .= $entity_type . ', ' . $bundle . ', ' . $bundle_info['label'] . PHP_EOL;
      }
    }
    return $output;
  }

}
