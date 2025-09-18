<?php

namespace Drupal\eca\Plugin;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Trait for ECA plugins that have optional YAML support.
 */
trait FormFieldYamlTrait {

  /**
   * Adds YAML configuration fields to the form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $title
   *   The title.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $description
   *   The description.
   * @param int $weight
   *   The weight.
   */
  protected function buildYamlFormFields(array &$form, TranslatableMarkup $title, TranslatableMarkup $description, int $weight): void {
    $form['use_yaml'] = [
      '#type' => 'checkbox',
      '#title' => $title,
      '#description' => $description,
      '#default_value' => $this->configuration['use_yaml'],
      '#weight' => $weight,
    ];
    $form['validate_yaml'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Validate YAML to prevent this from being executed when invalid'),
      '#default_value' => $this->configuration['validate_yaml'],
      '#states' => [
        'visible' => [
          ':input[name="use_yaml"]' => ['checked' => TRUE],
        ],
      ],
      '#weight' => $weight + 1,
    ];
  }

}
