<?php

namespace Drupal\eca_base\Plugin\FieldWidgetAction;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\State\StateInterface;
use Drupal\eca\Entity\Eca;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Deriver for field widgets.
 */
final class EcaFieldWidgetDeriver extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The Drupal state.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id): EcaFieldWidgetDeriver {
    $instance = new self();
    $instance->state = $container->get('state');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    $this->derivatives = [];
    foreach (Eca::loadMultiple() as $model) {
      if ($model->status()) {
        foreach ($model->getUsedEvents() as $usedEvent) {
          if ($usedEvent->getPlugin()->getPluginId() === 'eca_base:eca_field_widget') {
            $this->derivatives[$usedEvent->getId()] = [
              'label' => $usedEvent->getLabel(),
            ] + $base_plugin_definition;
          }
        }
      }
    }
    return $this->derivatives;
  }

}
