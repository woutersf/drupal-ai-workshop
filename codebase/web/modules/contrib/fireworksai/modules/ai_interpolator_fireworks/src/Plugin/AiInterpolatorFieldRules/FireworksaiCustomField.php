<?php

namespace Drupal\ai_interpolator_fireworksai\Plugin\AiInterPolatorFieldRules;

use Drupal\ai_interpolator\PluginBaseClasses\CustomField;
use Drupal\ai_interpolator\PluginInterfaces\AiInterpolatorFieldRuleInterface;
use Drupal\ai_interpolator_fireworksai\FireworksaiApiTrait;

/**
 * The rules for a custom field.
 *
 * @AiInterpolatorFieldRule(
 *   id = "ai_interpolator_fireworksai_custom_field",
 *   title = @Translation("Fireworks AI Custom Field"),
 *   field_rule = "custom_field",
 * )
 */
class FireworksaiCustomField extends CustomField implements AiInterpolatorFieldRuleInterface {

  // Load all the logic.
  use FireworksaiApiTrait;

  /**
   * {@inheritDoc}
   */
  public $title = 'Fireworks AI Custom Field';

}
