<?php

namespace Drupal\ai_interpolator_fireworksai\Plugin\AiInterPolatorFieldRules;

use Drupal\ai_interpolator\PluginBaseClasses\Taxonomy;
use Drupal\ai_interpolator\PluginInterfaces\AiInterpolatorFieldRuleInterface;
use Drupal\ai_interpolator_fireworksai\FireworksaiApiTrait;

/**
 * The rules for a Taxonomy field.
 *
 * @AiInterpolatorFieldRule(
 *   id = "ai_interpolator_fireworksai_taxonomy",
 *   title = @Translation("Fireworks AI Taxonomy"),
 *   field_rule = "entity_reference",
 *   target = "taxonomy_term"
 * )
 */
class FireworksaiTaxonomy extends Taxonomy implements AiInterpolatorFieldRuleInterface {

  // Load all the logic.
  use FireworksaiApiTrait;

  /**
   * {@inheritDoc}
   */
  public $title = 'Fireworks AI Taxonomy';

}
