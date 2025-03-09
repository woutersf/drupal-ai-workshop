<?php

namespace Drupal\ai_interpolator_fireworksai;

use Drupal\ai_interpolator\PluginBaseClasses\Numeric;
use Drupal\ai_interpolator\PluginInterfaces\AiInterpolatorFieldRuleInterface;

/**
 * Helper for number chat base.
 */
class FireworksaiNumberBase extends Numeric implements AiInterpolatorFieldRuleInterface {

  // Load all the logic.
  use FireworksaiApiTrait;

  /**
   * {@inheritDoc}
   */
  public $title = 'Fireworks AI Numeric';

}
