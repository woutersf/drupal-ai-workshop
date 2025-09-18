<?php

namespace Drupal\eca_base\Plugin\AiFunctionCall;

use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\eca\Token\TokenInterface;
use Drupal\eca_base\BaseEvents;
use Drupal\eca_base\Event\ToolEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Plugin wrapper for AI tools derived from ECA custom events.
 */
#[FunctionCall(
  id: 'eca',
  function_name: 'placeholder',
  name: 'placeholder',
  description: 'placeholder',
  deriver: '\Drupal\eca_base\Plugin\AiFunctionCall\EcaDeriver',
)]
class Eca extends FunctionCallBase implements ExecutableFunctionCallInterface {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * The ECA-related token services.
   *
   * @var \Drupal\eca\Token\TokenInterface
   */
  protected TokenInterface $tokenService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->eventDispatcher = $container->get('event_dispatcher');
    $instance->tokenService = $container->get('eca.token_services');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $names = [];
    foreach ($this->pluginDefinition['context_definitions'] as $name => $context) {
      $names[] = $name;
      $this->tokenService->addTokenData($name, $this->getContextValue($name));
    }
    $event = new ToolEvent($this->pluginDefinition['wildcard']);
    $event->addTokenNamesFromString(implode(',', $names));
    $this->eventDispatcher->dispatch($event, BaseEvents::TOOL);
    $output = $event->getOutput();
    if ($output instanceof EntityAdapter) {
      $output = $output->getEntity();
    }
    if ($output instanceof DataTransferObject) {
      $output = $output->getValue();
    }
    if ($output instanceof EntityInterface) {
      $output = $output->toArray();
    }

    if (is_scalar($output)) {
      $this->stringOutput = (string) $output;
    }
    elseif ($output === NULL) {
      $this->stringOutput = 'undefined';
    }
    else {
      $this->stringOutput = Yaml::encode($output);
    }
  }

}
