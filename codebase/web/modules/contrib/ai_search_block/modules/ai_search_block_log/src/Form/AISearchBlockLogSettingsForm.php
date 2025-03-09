<?php

declare(strict_types=1);

namespace Drupal\ai_search_block_log\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for an ai search block log entity type.
 */
final class AISearchBlockLogSettingsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ai_search_block_log_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['settings'] = [
      '#markup' => $this->t('Settings form for an ai search block log entity type.'),
    ];
    $expiry = $this->configFactory()
      ->get('ai_search_block_log.settings')
      ->get('expiry');
    $form['expiration'] = [
      '#type' => 'select',
      '#title' => $this->t('Expiration'),
      '#default_value' => $expiry ?? 'week',
      '#description' => $this->t('This is the amount of time the system will keep the logs'),
      '#options' => [
        'day' => $this->t('1 day'),
        'week' => $this->t('1 week'),
        'month' => $this->t('1 month'),
        'year' => $this->t('1 year'),
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Save'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValues();
    $this->configFactory()
      ->getEditable('ai_search_block_log.settings')
      ->set('expiry', $values['expiration'])
      ->save();
    $this->messenger()
      ->addStatus($this->t('The configuration has been updated. The expiration of existing items will not be updated.'));
  }

}
