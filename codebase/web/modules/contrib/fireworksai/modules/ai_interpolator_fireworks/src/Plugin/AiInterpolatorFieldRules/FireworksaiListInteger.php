<?php

namespace Drupal\ai_interpolator_fireworksai\Plugin\AiInterPolatorFieldRules;

use Drupal\ai_interpolator_fireworksai\FireworksaiOptionsBase;

/**
 * The rules for a list_integer field.
 *
 * @AiInterpolatorFieldRule(
 *   id = "ai_interpolator_fireworksai_list_integer",
 *   title = @Translation("Fireworks AI List Integer"),
 *   field_rule = "list_integer",
 * )
 */
class FireworksaiListInteger extends FireworksaiOptionsBase {

  /**
   * {@inheritDoc}
   */
  public $title = 'Fireworks AI List Integer';

}
