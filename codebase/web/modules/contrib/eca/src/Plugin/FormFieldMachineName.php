<?php

namespace Drupal\eca\Plugin;

use Drupal\Core\Form\FormStateInterface;

/**
 * Trait of ECA plugins using textfields as machine names.
 */
class FormFieldMachineName {

  /**
   * Validates form element fields that are like machine names.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function validateElementsMachineName(array &$element, FormStateInterface $form_state): void {
    $value = $element['#value'];
    if ($value === '') {
      return;
    }
    if ($element['#eca_token_reference'] ?? FALSE) {
      // This field expects a token reference. Only characters, numbers,
      // hyphens, colons, and underscores are allowed.
      if (!preg_match('/^[A-Za-z0-9:_\-]+$/', $value)) {
        $form_state->setError($element, t('The %name must be a machine-readable name (characters, numbers, hyphens, colons, and underscores only).', [
          '%name' => $element['#title'],
        ]));
      }
      return;
    }
    if (!($element['#eca_token_replacement'] ?? FALSE)) {
      // This field requires a machine name that doesn't support token
      // replacement. Only characters, numbers, hyphens, and underscores are
      // allowed.
      if (!preg_match('/^[A-Za-z0-9_\-]+$/', $value)) {
        $form_state->setError($element, t('The %name must be a machine-readable name (characters, numbers, hyphens, colons, and underscores only).', [
          '%name' => $element['#title'],
        ]));
      }
      return;
    }
    if (!preg_match('/^[\[\]A-Za-z0-9:_\-]+$/', $value)) {
      $form_state->setError($element, t('The %name must be a machine-readable name (characters, numbers, hyphens, colons, and underscores only). The name may also contain tokens, i.e. "[entity:value]" is allowed as well.', [
        '%name' => $element['#title'],
      ]));
    }
  }

}
