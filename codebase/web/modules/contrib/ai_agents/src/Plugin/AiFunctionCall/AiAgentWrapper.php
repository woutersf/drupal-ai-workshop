<?php

namespace Drupal\ai_agents\Plugin\AiFunctionCall;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai\Utility\ContextDefinitionNormalizer;
use Drupal\ai_agents\PluginInterfaces\AiAgentFunctionInterface;
use Drupal\ai_agents\PluginInterfaces\AiAgentInterface;
use Drupal\ai_agents\PluginInterfaces\ConfigAiAgentInterface;
use Drupal\ai_agents\PluginManager\AiAgentManager;
use Drupal\ai_agents\Task\Task;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Wraps Drupal agent plugins to be used as custom tools.
 */
class AiAgentWrapper extends FunctionCallBase implements ExecutableFunctionCallInterface, ContainerFactoryPluginInterface, AiAgentFunctionInterface {

  /**
   * The agent plugin to translate into a tool.
   *
   * @var \Drupal\ai_agents\PluginInterfaces\AiAgentInterface
   *   The agent plugin.
   */
  protected $agent;

  /**
   * The AI provider manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected AiProviderPluginManager $aiProvider;

  /**
   * The AI Agent plugin manager.
   *
   * @var \Drupal\ai_agents\PluginManager\AiAgentManager
   */
  protected AiAgentManager $aiAgentManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The output string.
   *
   * @var string
   */
  protected string $output = "";

  /**
   * The runner id.
   *
   * @var string
   */
  protected string $runnerId = "";

  /**
   * Possible tokens to inject.
   *
   * @var array
   */
  protected array $tokens = [];

  /**
   * Constructor.
   *
   * @param array $configuration
   *   The configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\ai\Utility\ContextDefinitionNormalizer $context_definition
   *   The context definition normalizer.
   * @param \Drupal\ai_agents\PluginManager\AiAgentManager $aiAgentManager
   *   The AI Agent plugin manager.
   * @param \Drupal\ai\AiProviderPluginManager $aiProvider
   *   The AI Provider plugin manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    $configuration,
    $plugin_id,
    $plugin_definition,
    ContextDefinitionNormalizer $context_definition,
    AiAgentManager $aiAgentManager,
    AiProviderPluginManager $aiProvider,
    EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $context_definition);
    $this->aiProvider = $aiProvider;
    $this->aiAgentManager = $aiAgentManager;
    $this->agent = $this->aiAgentManager->createInstance($this->pluginDefinition['function_name']);
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): FunctionCallInterface|static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai.context_definition_normalizer'),
      $container->get('plugin.manager.ai_agents'),
      $container->get('ai.provider'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getAgent(): AiAgentInterface {
    return $this->agent;
  }

  /**
   * {@inheritdoc}
   */
  public function setAgent(AiAgentInterface $agent) {
    $this->agent = $agent;
  }

  /**
   * Sets the runner id.
   */
  public function setRunnerId($runner_id) {
    $this->runnerId = $runner_id;
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    // Get the contexts.
    $prompt = $this->getContextValue('prompt');
    $files = $this->getContextValue('files');

    $defaults = $this->aiProvider->getDefaultProviderForOperationType('chat_with_tools');
    if (empty($defaults['provider_id']) || empty($defaults['model_id'])) {
      throw new \exception("No AI provider is configured for 'Chat with Tools/Function Calling'. Please configure in the AI default settings");
    }

    $this->agent = $this->aiAgentManager->createInstance($this->pluginDefinition['function_name']);
    // Run the agents plugin.
    $task = new Task($prompt);
    // Set the files if any.
    if (!empty($files)) {
      // Load each file and add it to the task.
      $task_files = $this->entityTypeManager->getStorage('file')->loadMultiple($files);
      $task->setFiles($task_files);
    }
    $this->agent->setRunnerId($this->runnerId);
    $this->agent->setTask($task);
    $this->agent->setAiProvider($this->aiProvider->createInstance($defaults['provider_id']));
    $this->agent->setModelName($defaults['model_id']);
    $this->agent->setAiConfiguration([]);
    $this->agent->setCreateDirectly(TRUE);
    if ($this->agent instanceof ConfigAiAgentInterface) {
      $this->agent->setTokenContexts($this->tokens);
    }
    $solvability = $this->agent->determineSolvability();
    if ($solvability == AiAgentInterface::JOB_SHOULD_ANSWER_QUESTION) {
      $this->output = $this->agent->answerQuestion();
    }
    elseif ($solvability == AiAgentInterface::JOB_SOLVABLE) {
      $this->output = $this->agent->solve();
    }
    else {
      $this->output = 'I am not able to solve this task.';
    }
  }

  /**
   * Set tokens for the agent.
   *
   * @param array $tokens
   *   The tokens to set.
   */
  public function setTokens(array $tokens) {
    $this->tokens = $tokens;
  }

  /**
   * {@inheritdoc}
   */
  public function getReadableOutput(): string {
    return $this->output;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutput(): string {
    return $this->output;
  }

  /**
   * Magic method to run the agent plugin's methods.
   *
   * @param string $name
   *   The method name.
   * @param array $arguments
   *   The method arguments.
   */
  public function __call($name, $arguments) {
    return $this->agent->$name(...$arguments);
  }

  /**
   * Magic method to set properties.
   *
   * @param string $name
   *   The property name.
   * @param mixed $value
   *   The property value.
   */
  public function __set($name, $value) {
    $this->$name = $value;
  }

}
