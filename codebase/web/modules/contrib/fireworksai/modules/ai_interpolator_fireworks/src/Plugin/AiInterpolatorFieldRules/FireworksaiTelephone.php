<?php

namespace Drupal\ai_interpolator_fireworksai\Plugin\AiInterPolatorFieldRules;

use Drupal\ai_interpolator\PluginBaseClasses\Telephone;
use Drupal\ai_interpolator\PluginInterfaces\AiInterpolatorFieldRuleInterface;
use Drupal\ai_interpolator_fireworksai\FireworksaiApiTrait;

/**
 * The rules for a telephone field.
 *
 * @AiInterpolatorFieldRule(
 *   id = "ai_interpolator_fireworksai_telephone",
 *   title = @Translation("Fireworks AI Telephone"),
 *   field_rule = "telephone",
 * )
 */
class FireworksaiTelephone extends Telephone implements AiInterpolatorFieldRuleInterface {
  // Get the trait for Fireworks AI.
  use FireworksaiApiTrait;

  /**
   * {@inheritDoc}
   */
  public $title = 'Fireworks AI Telephone';

}
