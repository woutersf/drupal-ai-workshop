<?php

namespace Drupal\ai_agents\PluginManager;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Utility\Token;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager;
use Drupal\ai_agents\Attribute\AiAgent;
use Drupal\ai_agents\PluginBase\AiAgentEntityWrapper;
use Drupal\ai_agents\PluginInterfaces\AiAgentInterface;
use Drupal\ai_agents\Service\AgentHelper;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides an AI Agent plugin manager.
 *
 * @see \Drupal\ai_agents\Attribute\AiAgent
 * @see \Drupal\ai_agents\PluginInterfaces\AiAgentInterface
 * @see plugin_api
 */
class AiAgentManager extends DefaultPluginManager {

  /**
   * Constructs an AI Agents object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager $functionCallPluginManager
   *   The function call plugin manager.
   * @param \Drupal\ai_agents\Service\AgentHelper $agentHelper
   *   The agent helper.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Drupal\ai\AiProviderPluginManager $aiProviderPluginManager
   *   The AI provider plugin manager.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountProxyInterface $currentUser,
    protected FunctionCallPluginManager $functionCallPluginManager,
    protected AgentHelper $agentHelper,
    protected Token $token,
    protected EventDispatcherInterface $eventDispatcher,
    protected AiProviderPluginManager $aiProviderPluginManager,
  ) {
    parent::__construct(
      'Plugin/AiAgent',
      $namespaces,
      $module_handler,
      AiAgentInterface::class,
      AiAgent::class,
    );
    $this->mergeAgentConfigurations();
    $this->alterInfo('ai_agents_info');
    $this->setCacheBackend($cache_backend, 'ai_agents_plugins');
  }

  /**
   * Creates a plugin instance of a AI Agent.
   *
   * @param string $plugin_id
   *   The ID of the plugin being instantiated.
   * @param array $configuration
   *   An array of configuration relevant to the plugin instance.
   *
   * @return \Drupal\ai\Service\FunctionCalling\FunctionCallInterface
   *   The function call.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   If the instance cannot be created, such as if the ID is invalid.
   */
  public function createInstance($plugin_id, array $configuration = []): AiAgentInterface {
    // Check if the plugin is an action plugin.
    if (isset($this->definitions[$plugin_id]['custom_type']) && $this->definitions[$plugin_id]['custom_type'] === 'config') {
      $instance = new AiAgentEntityWrapper($this->entityTypeManager->getStorage('ai_agent')->load($plugin_id), $this->currentUser, $this->entityTypeManager, $this->functionCallPluginManager, $this->agentHelper, $this->token, $this->eventDispatcher, $this->aiProviderPluginManager);
      return $instance;
    }
    return parent::createInstance($plugin_id, $configuration);
  }

  /**
   * Merge Agent Configurations into this custom plugin system.
   */
  protected function mergeAgentConfigurations(): void {
    $plugins = $this->getDefinitions();
    foreach ($plugins as $plugin_id => $plugin) {
      $this->definitions[$plugin_id] = $plugin;
    }

    // If this module is installing, the entity type will not yet exist, so we
    // don't want to trigger an error.
    if ($this->entityTypeManager->hasDefinition('ai_agent')) {
      $agent_configurations = $this->entityTypeManager->getStorage('ai_agent')
        ->loadMultiple();
      foreach ($agent_configurations as $id => $entity) {
        // Modify the plugin definition structure to fit your system.
        if (!isset($this->definitions[$id])) {
          $definition['id'] = $id;
          $definition['label'] = $entity->label();
          $definition['custom_type'] = 'config';
          // Add the Config Plugin into our system.
          $this->definitions[$id] = $definition;
        }
      }
    }
  }

  /**
   * Finds plugin definitions.
   *
   * @return array
   *   List of definitions to store in cache.
   */
  protected function findDefinitions():array {
    $definitions = parent::findDefinitions();

    foreach ($definitions as $id => $definition) {
      if (!empty($definition['module_dependencies'])) {

        // Check if all modules are installed, otherwise remove this.
        foreach ($definition['module_dependencies'] as $module) {
          if (!$this->providerExists($module)) {
            unset($definitions[$id]);
            break;
          }
        }
      }
    }

    return $definitions;
  }

}
