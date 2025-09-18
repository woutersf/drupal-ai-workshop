<?php

namespace Drupal\ai_search\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\ai\AiVdbProviderPluginManager;
use Drupal\ai\Enum\VdbSimilarityMetrics;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listens for config entity creation events.
 */
class NewServerEventSubscriber implements EventSubscriberInterface {

  /**
   * The constructor.
   *
   * @param \Drupal\ai\AiVdbProviderPluginManager $vdbProvider
   *   The VDB provider.
   */
  public function __construct(
    protected readonly AiVdbProviderPluginManager $vdbProvider,
  ) {
  }

  /**
   * Makes sure that the server is setup on config import/recipe apply.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The config entity save event.
   */
  public function onConfigSave(ConfigCrudEvent $event) {
    $config = $event->getConfig();
    // Only check configs that are search api servers.
    if (str_starts_with($config->getName(), 'search_api.server.')) {
      $data = $config->getRawData();
      // Only VDB providers apply.
      if (
        isset($data['backend']) && $data['backend'] == 'search_api_ai_search' &&
        isset($data['backend_config']['database']) && $this->vdbProvider->getDefinition($data['backend_config']['database'])
      ) {
        $vdb_provider = $this->vdbProvider->createInstance($data['backend_config']['database']);
        // Check if the collection exists.
        $collections = $vdb_provider->getCollections($data['backend_config']['database_settings']['database_name']);
        if (is_array($collections) && in_array($data['backend_config']['database_settings']['collection'], $collections)) {
          return;
        }
        // Otherwise create the collection.
        $vdb_provider->createCollection(
          $data['backend_config']['database_settings']['collection'],
          $data['backend_config']['embeddings_engine_configuration']['dimensions'],
          VdbSimilarityMetrics::from($data['backend_config']['database_settings']['metric']),
          $data['backend_config']['database_settings']['database_name'],
        );
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      ConfigEvents::SAVE => 'onConfigSave',
    ];
  }

}
