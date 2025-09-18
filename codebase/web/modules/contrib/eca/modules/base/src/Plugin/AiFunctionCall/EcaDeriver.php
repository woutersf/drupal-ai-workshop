<?php

namespace Drupal\eca_base\Plugin\AiFunctionCall;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Deriver for AI tools derives from ECA custom events.
 */
final class EcaDeriver extends DeriverBase implements ContainerDeriverInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected StateInterface $state,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id): EcaDeriver {
    return new EcaDeriver(
      $container->get('entity_type.manager'),
      $container->get('state'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    $this->derivatives = [];

    /** @var \Drupal\eca\Entity\EcaStorage $eca_storage */
    $eca_storage = $this->entityTypeManager->getStorage('eca');
    $subscribed = current($this->state->get('eca.subscribed', [])['eca_base.tool'] ?? []);
    if (!$subscribed) {
      return $this->derivatives;
    }
    foreach ($subscribed as $eca_id => $wildcards) {
      /** @var \Drupal\eca\Entity\Eca|null $eca */
      $eca = $eca_storage->load($eca_id);
      if (!$eca) {
        // If an ECA model got deleted, we may end up here and then ignore
        // this model as it not longer exists.
        continue;
      }
      foreach ($wildcards as $eca_event_id => $wildcard) {
        if (!($ecaEvent = $eca->getEcaEvent($eca_event_id))) {
          continue;
        }
        $id = strtolower(implode(':', [$eca_id, $eca_event_id]));
        $fullId = implode(':', [$base_plugin_definition['id'], $id]);
        $this->derivatives[$id] = [
          'id' => $fullId,
          'name' => $ecaEvent->getLabel(),
          'function_name' => str_replace(':', '_', $fullId),
          'description' => $ecaEvent->getConfiguration()['description'] ?? 'unavailable',
          'context_definitions' => [],
          'wildcard' => $wildcard,
        ] + $base_plugin_definition;
        $arguments = Yaml::decode($ecaEvent->getConfiguration()['arguments']) ?? [];
        foreach ($arguments as $name => $argument) {
          if (!isset($argument['data_type'])) {
            continue;
          }
          if (str_starts_with($argument['data_type'], 'entity:')) {
            $this->derivatives[$id]['context_definitions'][$name] = new EntityContextDefinition(
              $argument['data_type'],
              $argument['label'] ?? 'No label',
              $argument['required'] ?? FALSE,
              FALSE,
              $argument['description'] ?? '',
            );
          }
          else {
            $this->derivatives[$id]['context_definitions'][$name] = new ContextDefinition(
              $argument['data_type'],
              $argument['label'] ?? 'No label',
              $argument['required'] ?? FALSE,
              FALSE,
              $argument['description'] ?? '',
            );
          }
        }
      }
    }
    return $this->derivatives;
  }

}
