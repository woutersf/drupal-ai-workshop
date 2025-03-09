<?php

namespace Drupal\ai_interpolator_fireworksai;

use Drupal\ai_interpolator\PluginBaseClasses\ComplexTextChat;
use Drupal\ai_interpolator\PluginInterfaces\AiInterpolatorFieldRuleInterface;

/**
 * Helper for complex chat base.
 */
class FireworksaiComplexTextChatBase extends ComplexTextChat implements AiInterpolatorFieldRuleInterface {

  // Load all the logic.
  use FireworksaiApiTrait;

  /**
   * {@inheritDoc}
   */
  public $title = 'Fireworks AI Text Chat';

  /**
   * {@inheritDoc}
   */
  public function helpText() {
    return "This rule is asking for JSON in the background, if you want to use smaller models, please use the easy version.";
  }

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "Based on the context text create a summary between 100 and 300 characters.\n\nContext:\n{{ context }}";
  }

}
