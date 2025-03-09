<?php

namespace Drupal\ai_api\PluginManager;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\ai_api\Attribute\AiApiAccessPoint;
use Drupal\ai_api\PluginInterface\AiApiAccessPointInterface;

/**
 * Provides an AI Access Pont plugin manager.
 *
 * @see \Drupal\ai_api\Attribute\AiApiAccessPoint
 * @see \Drupal\ai_api\PluginInterfaces\AiApiAccessPointInterface
 * @see plugin_api
 */
class AiApiAccessPointManager extends DefaultPluginManager {

  /**
   * Constructs an AI Access Point object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/AiApiAccessPoint',
      $namespaces,
      $module_handler,
      AiApiAccessPointInterface::class,
      AiApiAccessPoint::class,
    );
    $this->alterInfo('ai_api_access_point_info');
    $this->setCacheBackend($cache_backend, 'ai_api_access_point_plugins');
  }

}
