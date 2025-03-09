<?php

namespace Drupal\ai_interpolator_fireworksai;

use Drupal\ai_interpolator\PluginBaseClasses\SimpleTextCompletion;
use Drupal\ai_interpolator\PluginInterfaces\AiInterpolatorFieldRuleInterface;

/**
 * Helper class for simple text completion.
 */
class FireworksaiSimpleTextCompletionBase extends SimpleTextCompletion implements AiInterpolatorFieldRuleInterface {

  // Load all the logic.
  use FireworksaiApiTrait;

  /**
   * {@inheritDoc}
   */
  public $title = 'Fireworksai Simple Text Completion';

  /**
   * {@inheritDoc}
   */
  public function helpText() {
    return "This is for text completion prompts without the Interpolator doing any additional processing. This is perfect for small models like Gemma.";
  }

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "Can you summarize the following for me.\n\nContext:\n{{ context }}";
  }

}
