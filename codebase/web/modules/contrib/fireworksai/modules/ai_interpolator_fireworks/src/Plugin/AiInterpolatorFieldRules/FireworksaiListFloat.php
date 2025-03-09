<?php

namespace Drupal\ai_interpolator_fireworksai\Plugin\AiInterPolatorFieldRules;

use Drupal\ai_interpolator_fireworksai\FireworksaiOptionsBase;

/**
 * The rules for a list_float field.
 *
 * @AiInterpolatorFieldRule(
 *   id = "ai_interpolator_fireworksai_list_float",
 *   title = @Translation("Fireworks AI List Float"),
 *   field_rule = "list_float",
 * )
 */
class FireworksaiListFloat extends FireworksaiOptionsBase {

  /**
   * {@inheritDoc}
   */
  public $title = 'Fireworks AI List Float';

}
