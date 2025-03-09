<?php

namespace Drupal\ai_interpolator_fireworksai\Plugin\AiInterPolatorFieldRules;

use Drupal\ai_interpolator_fireworksai\FireworksaiSimpleTextCompletionBase;

/**
 * The rules for a text_long_with_summary field.
 *
 * @AiInterpolatorFieldRule(
 *   id = "ai_interpolator_fireworksai_simple_text_completion_text_long_with_summary",
 *   title = @Translation("Fireworksai Simple Text Completion"),
 *   field_rule = "text_long_with_summary",
 * )
 */
class FireworksaiSimpleTextCompletionToTextLongSummary extends FireworksaiSimpleTextCompletionBase {

}
