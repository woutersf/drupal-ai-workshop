<?php

namespace Drupal\ai_agents\Plugin\AiFunctionCall;

use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai_agents\PluginInterfaces\AiAgentContextInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Plugin implementation of the describe config function.
 */
#[FunctionCall(
  id: 'ai_agent:get_config_schema',
  function_name: 'ai_agent_get_config_schema',
  name: 'Get Config Schema',
  description: 'This gets the Drupal configuration schema for a single schema.',
  group: 'information_tools',
  context_definitions: [
    'schema_id' => new ContextDefinition(
      data_type: 'string',
      label: 'Entity Type',
      description: 'The entity type to get the configuration schema for.',
      required: TRUE,
    ),
  ],
)]
class GetConfigSchema extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

  /**
   * The config typed data manager.
   *
   * @var \Drupal\Core\Config\TypedConfigManagerInterface
   */
  protected TypedConfigManagerInterface $typedConfigManager;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

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
    $instance->typedConfigManager = $container->get('config.typed');
    $instance->currentUser = $container->get('current_user');
    return $instance;
  }

  /**
   * The schema.
   *
   * @var array
   */
  protected array $schema = [];

  /**
   * {@inheritdoc}
   */
  public function execute() {
    // Collect the context values.
    $schema_id = $this->getContextValue('schema_id');
    // We need to ensure the highest level of permissions here.
    // This is because we are accessing the config schema, which may not be
    // accessible to all users. Base tools will give more flexibility
    // in the future.
    if (!$this->currentUser->hasPermission('administer permissions')) {
      throw new \Exception('You do not have permission to access this function.');
    }
    // Check if the schema exists.
    if (!$this->typedConfigManager->hasDefinition($schema_id)) {
      throw new \InvalidArgumentException(sprintf('The schema "%s" does not exist.', $schema_id));
    }

    $this->schema = $this->typedConfigManager->getDefinition($schema_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getReadableOutput(): string {
    return Yaml::dump($this->schema, 10, 2);
  }

}
