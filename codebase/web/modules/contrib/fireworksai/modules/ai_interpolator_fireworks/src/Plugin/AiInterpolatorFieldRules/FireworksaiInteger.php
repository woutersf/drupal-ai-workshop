<?php

namespace Drupal\ai_interpolator_fireworksai\Plugin\AiInterPolatorFieldRules;

use Drupal\ai_interpolator_fireworksai\FireworksaiNumberBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * The rules for a integer field.
 *
 * @AiInterpolatorFieldRule(
 *   id = "ai_interpolator_fireworksai_integer",
 *   title = @Translation("Fireworks AI Integer"),
 *   field_rule = "integer",
 * )
 */
class FireworksaiInteger extends FireworksaiNumberBase {

  /**
   * {@inheritDoc}
   */
  public $title = 'Fireworks AI Integer';

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "Based on the context text add a sentiment rating between {{ min }} and {{ max }}, where {{ min }} means really negative sentiment and {{ max }} means really great sentiment. Answer with a full number.\n\nContext:\n{{ context }}";
  }

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition) {
    // Since we allow any type of number we round it.
    $values = array_map(fn($value) => round($value, 0), $values);
    // Then set the value.
    $entity->set($fieldDefinition->getName(), $values);
    return TRUE;
  }

}
