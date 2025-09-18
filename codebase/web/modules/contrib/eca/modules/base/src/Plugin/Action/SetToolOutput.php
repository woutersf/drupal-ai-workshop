<?php

namespace Drupal\eca_base\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca_base\Event\ToolEvent;

/**
 * Action plugin to set the output for the tool event.
 *
 * @Action(
 *   id = "eca_set_tool_output",
 *   label = @Translation("Set tool output"),
 *   description = @Translation("This action sets the output for the tool event."),
 *   eca_version_introduced = "2.1.0"
 * )
 */
class SetToolOutput extends ConfigurableActionBase {

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = AccessResult::allowedIf($this->getEvent() instanceof ToolEvent);
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    /** @var \Drupal\eca_base\Event\ToolEvent $event */
    $event = $this->getEvent();
    $output = $this->configuration['output'];
    if ($this->tokenService->hasTokenData($output)) {
      $event->setOutput($this->tokenService->getTokenData($output));
    }
    else {
      $event->setOutput($this->tokenService->replaceClear($output));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'output' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['output'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Tool output'),
      '#default_value' => $this->configuration['output'],
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['output'] = $form_state->getValue('output');
    parent::submitConfigurationForm($form, $form_state);
  }

}
