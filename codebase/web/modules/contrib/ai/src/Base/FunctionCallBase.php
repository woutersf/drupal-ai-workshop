<?php

namespace Drupal\ai\Base;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Plugin\ContextAwarePluginTrait;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInput;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionOutput;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai\Utility\ContextDefinitionNormalizer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Function call base class.
 */
abstract class FunctionCallBase extends PluginBase implements FunctionCallInterface, ContainerFactoryPluginInterface, ContextAwarePluginInterface {

  use StringTranslationTrait;
  use ContextAwarePluginTrait;

  /**
   * The context definition normalizer service.
   */
  protected ContextDefinitionNormalizer $contextDefinitionNormalizer;

  /**
   * The tools id.
   *
   * @var string
   */
  protected string $toolsId = "";

  /**
   * The output.
   *
   * @var string
   */
  protected string $stringOutput = "";

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
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected ContextDefinitionNormalizer $context_definition_normalizer,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->contextDefinitionNormalizer = $context_definition_normalizer;
  }

  /**
   * Load from dependency injection container.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): FunctionCallInterface|static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai.context_definition_normalizer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getToolsId(): string {
    return $this->toolsId;
  }

  /**
   * {@inheritdoc}
   */
  public function setToolsId(string $tools_id) {
    $this->toolsId = $tools_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctionName(): string {
    return $this->pluginDefinition['function_name'];
  }

  /**
   * {@inheritdoc}
   */
  public function populateValues(ToolsFunctionOutput $output) {
    foreach ($output->getArguments() as $argument) {
      $property_name = $argument->getName();
      // Tmp fix?.
      $property_name = str_replace('__colon__', ':', $property_name);
      // @todo What happens if this fails to pass constraints?
      $this->setContextValue($property_name, $argument->getValue());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getReadableOutput(): string {
    return $this->stringOutput;
  }

  /**
   * {@inheritdoc}
   */
  public function setOutput(string $output): void {
    $this->stringOutput = $output;
  }

  /**
   * {@inheritdoc}
   */
  public function populateChildValue(FunctionCallInterface $child, array $values): array {
    $items = [];
    foreach ($values as $props) {
      $item = clone $child;
      foreach ($props as $prop => $value) {
        if (property_exists($child, $prop)) {
          $item->$prop = $value;
        }
      }
      $items[] = $item;
    }
    return $items;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize(): ToolsFunctionInput {
    // Check all the attributes of the function call.
    $function_attributes = $this->pluginDefinition;
    // Create the function first.
    $function = new ToolsFunctionInput($function_attributes['function_name'], []);
    $function->setDescription($function_attributes['description']);
    if ($this->getContextDefinitions()) {
      $properties = $this->contextDefinitionNormalizer->normalize($this->getContextDefinitions());
      foreach ($properties as $property) {
        $function->setProperty($property);
      }
    }
    return $function;
  }

}
