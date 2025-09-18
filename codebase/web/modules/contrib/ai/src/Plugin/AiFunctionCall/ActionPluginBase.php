<?php

namespace Drupal\ai\Plugin\AiFunctionCall;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Action\ActionManager;
use Drupal\Core\Action\ActionPluginCollection;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Plugin\AiFunctionCall\Derivative\ActionPluginDeriver;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai\Utility\ContextDefinitionNormalizer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the action plugin function.
 */
#[FunctionCall(
  id: 'action_plugin',
  function_name: 'action_plugin',
  name: new TranslatableMarkup('Action Plugin Wrapper'),
  description: '',
  deriver: ActionPluginDeriver::class
)]
class ActionPluginBase extends FunctionCallBase implements ExecutableFunctionCallInterface {

  /**
   * The action manager service.
   *
   * @var \Drupal\Core\Action\ActionManager
   */
  protected ActionManager $actionManager;

  /**
   * The action plugin.
   *
   * @var \Drupal\Core\Action\ActionPluginCollection
   */
  protected $pluginCollection;

  /**
   * The status of the action execution.
   *
   * @var string|null
   */
  protected $executionStatus;

  /**
   * The error message, if any.
   *
   * @var string|null
   */
  protected $errorMessage;

  /**
   * Constructs a FunctionCall plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\ai\Utility\ContextDefinitionNormalizer $context_definition_normalizer
   *   The context definition normalizer service.
   * @param \Drupal\Core\Action\ActionManager $action_manager
   *   The action manager service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected ContextDefinitionNormalizer $context_definition_normalizer,
    protected ActionManager $action_manager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $context_definition_normalizer);
    $this->actionManager = $action_manager;
  }

  /**
   * Load from dependency injection container.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): FunctionCallInterface|static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai.context_definition_normalizer'),
      $container->get('plugin.manager.action')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    try {
      $action_plugin = $this->getPluginCollection()->get($this->getDerivativeId());
      if ($action_plugin instanceof ConfigurableInterface) {
        $params = [];
        $configuration = $action_plugin->getConfiguration();
        // If context keys exist, not in configuration, set to execute.
        foreach ($this->getContextValues() as $key => $value) {
          if (isset($configuration[$key])) {
            $configuration[$key] = $value;
          }
          else {
            $params[$key] = $value;
          }
        }
        $action_plugin->setConfiguration($configuration);
      }
      else {
        $params = $this->getContextValues();
      }
      $params = array_values($params);
      // @todo Add access check.
      // @todo Count params? or find something better.
      // $this->actionPlugin->access($entity, \Drupal::currentUser());
      $action_plugin->execute(...$params);

      // Set the execution status to success if no exception occurs.
      $this->executionStatus = 'success';
      $this->errorMessage = NULL;

    }
    catch (\Exception $e) {
      // Set the execution status to failed and store the error message.
      $this->executionStatus = 'failed';
      $this->errorMessage = $e->getMessage();

    }
  }

  /**
   * {@inheritdoc}
   */
  public function getReadableOutput(): string {
    // Get the action ID (derivative ID).
    $action_id = $this->getDerivativeId() ?? 'unknown';

    // Default to 'not executed' if the action hasn't run.
    $status = $this->executionStatus ?? 'not executed';
    $output = "Action '$action_id' status: $status";

    // Append the error message if the action failed.
    if ($status === 'failed' && !empty($this->errorMessage)) {
      $output .= "\nError: " . $this->errorMessage;
    }

    return $output;
  }

  /**
   * Encapsulates the creation of the action's LazyPluginCollection.
   *
   * @return \Drupal\Component\Plugin\LazyPluginCollection
   *   The action's plugin collection.
   */
  protected function getPluginCollection() {
    if (!$this->pluginCollection) {
      $this->pluginCollection = new ActionPluginCollection($this->actionManager, $this->getDerivativeId(), []);
    }
    return $this->pluginCollection;
  }

}
