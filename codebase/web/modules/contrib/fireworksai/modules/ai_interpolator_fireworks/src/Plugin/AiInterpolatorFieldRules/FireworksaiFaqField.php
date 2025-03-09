<?php

namespace Drupal\ai_interpolator_fireworksai\Plugin\AiInterPolatorFieldRules;

use Drupal\ai_interpolator\PluginBaseClasses\FaqField;
use Drupal\ai_interpolator\PluginInterfaces\AiInterpolatorFieldRuleInterface;
use Drupal\ai_interpolator_fireworksai\FireworksaiApiTrait;

/**
 * The rules for a FAQ field.
 *
 * @AiInterpolatorFieldRule(
 *   id = "ai_interpolator_fireworksai_faq_field",
 *   title = @Translation("Fireworks AI FAQ Field"),
 *   field_rule = "faqfield",
 * )
 */
class FireworksaiFaqField extends FaqField implements AiInterpolatorFieldRuleInterface {

  // Load all the logic.
  use FireworksaiApiTrait;

  /**
   * {@inheritDoc}
   */
  public $title = 'Fireworks AI FAQ Field';

}
