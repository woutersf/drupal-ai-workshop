<?php

namespace Drupal\ai_interpolator_fireworksai\Plugin\AiInterPolatorFieldRules;

use Drupal\ai_interpolator\PluginBaseClasses\Email;
use Drupal\ai_interpolator\PluginInterfaces\AiInterpolatorFieldRuleInterface;
use Drupal\ai_interpolator_fireworksai\FireworksaiApiTrait;

/**
 * The rules for a E-mail field.
 *
 * @AiInterpolatorFieldRule(
 *   id = "ai_interpolator_fireworksai_email",
 *   title = @Translation("Fireworks AI E-Mail"),
 *   field_rule = "email",
 * )
 */
class FireworksaiEmail extends Email implements AiInterpolatorFieldRuleInterface {

  // Load all the logic.
  use FireworksaiApiTrait;

  /**
   * {@inheritDoc}
   */
  public $title = 'Fireworks AI E-Mail';

}
