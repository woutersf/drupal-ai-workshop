<?php

namespace Drupal\ai_interpolator_fireworksai\Plugin\AiInterPolatorFieldRules;

use Drupal\ai_interpolator\PluginBaseClasses\OfficeHours;
use Drupal\ai_interpolator\PluginInterfaces\AiInterpolatorFieldRuleInterface;
use Drupal\ai_interpolator_fireworksai\FireworksaiApiTrait;

/**
 * The rules for a office_hours field.
 *
 * @AiInterpolatorFieldRule(
 *   id = "ai_interpolator_fireworksai_office_hours",
 *   title = @Translation("Fireworks AI Office Hours"),
 *   field_rule = "office_hours",
 * )
 */
class FireworksaiOfficeHours extends OfficeHours implements AiInterpolatorFieldRuleInterface {

  // Load all the logic.
  use FireworksaiApiTrait;

  /**
   * {@inheritDoc}
   */
  public $title = 'Fireworks AI Office Hours';

}
