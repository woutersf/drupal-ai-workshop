<?php

namespace Drupal\ai_interpolator_fireworksai\Plugin\AiInterPolatorFieldRules;

use Drupal\ai_interpolator_fireworksai\FireworksaiNumberBase;

/**
 * The rules for a decimal field.
 *
 * @AiInterpolatorFieldRule(
 *   id = "ai_interpolator_fireworksai_decimal",
 *   title = @Translation("Fireworks AI Decimal"),
 *   field_rule = "decimal",
 * )
 */
class FireworksaiDecimal extends FireworksaiNumberBase {

  /**
   * {@inheritDoc}
   */
  public $title = 'Fireworks AI Decimal';

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "Based on the context text add a sentiment rating between {{ min }} and {{ max }}, where {{ min }} means really negative sentiment and {{ max }} means really great sentiment. You can answer with decimals as well for more exactness.\n\nContext:\n{{ context }}";
  }

}
