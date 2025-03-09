<?php

namespace Drupal\ai_api\PluginBase;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai_api\PluginInterface\AiApiAccessPointInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class to take case of just some general stuff.
 */
abstract class BaseAccessPoint extends PluginBase implements AiApiAccessPointInterface, ContainerFactoryPluginInterface {

  /**
   * Constructor.
   */
  public function __construct(
    protected AiProviderPluginManager $aiProvider,
  ) {
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $container->get('ai.provider'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getId() {
    return $this->pluginDefinition['id'];
  }

  /**
   * {@inheritDoc}
   */
  public function getModuleName() {
    return $this->pluginDefinition['provider'];
  }

  /**
   * {@inheritDoc}
   */
  public function getOperationType() {
    return $this->pluginDefinition['operation'];
  }

}
