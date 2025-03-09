<?php

namespace Drupal\ai_interpolator_fireworksai\Plugin\AiInterPolatorFieldRules;

use Drupal\ai_interpolator_fireworksai\FireworksaiComplexTextChatBase;

/**
 * The rules for a string field.
 *
 * @AiInterpolatorFieldRule(
 *   id = "ai_interpolator_fireworksai_text_chat_string",
 *   title = @Translation("Fireworks AI Text Chat"),
 *   field_rule = "string",
 * )
 */
class FireworksaiTextChatToString extends FireworksaiComplexTextChatBase {

}
