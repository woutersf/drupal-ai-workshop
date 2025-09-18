<?php

namespace Drupal\eca_base\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\eca_base\Event\FieldWidgetEvent;

/**
 * Action plugin to set the value for the field widget event.
 *
 * @Action(
 *   id = "eca_set_field_widget_value",
 *   label = @Translation("Set field widget value"),
 *   description = @Translation("This action sets the value for the field widget event."),
 *   eca_version_introduced = "2.1.11"
 * )
 */
class SetWidgetValue extends ConfigurableActionBase {

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = AccessResult::allowedIf($this->getEvent() instanceof FieldWidgetEvent);
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    /** @var \Drupal\eca_base\Event\FieldWidgetEvent $event */
    $event = $this->getEvent();
    $value = $this->configuration['widget_value'];
    if ($this->tokenService->hasTokenData($value)) {
      $value = $this->tokenService->getTokenData($value);
    }
    else {
      $value = $this->tokenService->replaceClear($value);
    }
    if ($value instanceof DataTransferObject) {
      $value = $value->getValue();
      if (is_array($value)) {
        if (isset($value['_string_representation'])) {
          $value = $value['_string_representation'];
        }
        elseif (isset($value['values'])) {
          $value = $value['values'];
        }
      }
    }
    $event->setWidgetValue($value);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'widget_value' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['widget_value'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Field widget value'),
      '#default_value' => $this->configuration['widget_value'],
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['widget_value'] = $form_state->getValue('widget_value');
    parent::submitConfigurationForm($form, $form_state);
  }

}
