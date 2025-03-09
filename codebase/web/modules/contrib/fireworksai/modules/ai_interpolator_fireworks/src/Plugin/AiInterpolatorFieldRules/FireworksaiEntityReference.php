<?php

namespace Drupal\ai_interpolator_fireworksai\Plugin\AiInterPolatorFieldRules;

use Drupal\ai_interpolator\PluginBaseClasses\EntityReference;
use Drupal\ai_interpolator\PluginInterfaces\AiInterpolatorFieldRuleInterface;
use Drupal\ai_interpolator_fireworksai\FireworksaiApiTrait;

/**
 * The rules for an entity reference field.
 *
 * @AiInterpolatorFieldRule(
 *   id = "ai_interpolator_fireworksai_entity_reference",
 *   title = @Translation("Fireworks AI Entity Reference"),
 *   field_rule = "entity_reference",
 *   target = "any"
 * )
 */
class FireworksaiEntityReference extends EntityReference implements AiInterpolatorFieldRuleInterface {

  use FireworksaiApiTrait;

  /**
   * {@inheritDoc}
   */
  public $title = 'Fireworks AI Entity Reference';
}
