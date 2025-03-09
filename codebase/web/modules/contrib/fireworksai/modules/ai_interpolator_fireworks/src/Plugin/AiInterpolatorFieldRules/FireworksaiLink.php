<?php

namespace Drupal\ai_interpolator_fireworksai\Plugin\AiInterPolatorFieldRules;

use Drupal\ai_interpolator\PluginBaseClasses\Link;
use Drupal\ai_interpolator\PluginInterfaces\AiInterpolatorFieldRuleInterface;
use Drupal\ai_interpolator_fireworksai\FireworksaiApiTrait;

/**
 * The rules for a Link field.
 *
 * @AiInterpolatorFieldRule(
 *   id = "ai_interpolator_fireworksai_link",
 *   title = @Translation("Fireworks AI Link"),
 *   field_rule = "link",
 * )
 */
class FireworksaiLink extends Link implements AiInterpolatorFieldRuleInterface {
  // Load all the logic.
  use FireworksaiApiTrait;

  /**
   * {@inheritDoc}
   */
  public $title = 'Fireworks AI Link';

}
