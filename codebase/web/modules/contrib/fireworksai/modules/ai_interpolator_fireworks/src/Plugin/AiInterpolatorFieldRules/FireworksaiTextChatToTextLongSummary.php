<?php

namespace Drupal\ai_interpolator_fireworksai\Plugin\AiInterPolatorFieldRules;

use Drupal\ai_interpolator_fireworksai\FireworksaiComplexTextChatBase;

/**
 * The rules for a text_long_with_summary field.
 *
 * @AiInterpolatorFieldRule(
 *   id = "ai_interpolator_fireworksai_text_chat_text_long_with_summary",
 *   title = @Translation("Fireworks AI Text Chat"),
 *   field_rule = "text_long_with_summary",
 * )
 */
class FireworksaiTextChatToTextLongSummary extends FireworksaiComplexTextChatBase {

}
