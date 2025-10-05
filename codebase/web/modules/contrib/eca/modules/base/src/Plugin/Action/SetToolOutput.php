<?php

namespace Drupal\eca_base\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca\Plugin\FormFieldYamlTrait;
use Drupal\eca\Service\YamlParser;
use Drupal\eca_base\Event\ToolEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Exception\ParseException;

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


  use FormFieldYamlTrait;

  /**
   * The YAML parser.
   *
   * @var \Drupal\eca\Service\YamlParser
   */
  protected YamlParser $yamlParser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->yamlParser = $container->get('eca.service.yaml_parser');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = AccessResult::allowedIf($this->getEvent() instanceof ToolEvent);
    if ($result->isAllowed() && $this->configuration['use_yaml'] && $this->configuration['validate_yaml']) {
      try {
        $this->yamlParser->parse($this->configuration['config_value']);
      }
      catch (ParseException) {
        $result = AccessResult::forbidden('YAML data is not valid.');
      }
    }
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    /** @var \Drupal\eca_base\Event\ToolEvent $event */
    $event = $this->getEvent();
    $output = $this->configuration['output'];
    if ($this->configuration['use_yaml']) {
      try {
        $event->setOutput($this->yamlParser->parse($output));
      }
      catch (ParseException $e) {
        $this->logger->error('Tried parsing a config value in action "eca_set_tool_output" as YAML format, but parsing failed.');
        return;
      }
    }
    elseif ($this->tokenService->hasTokenData($output)) {
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
      'use_yaml' => FALSE,
      'validate_yaml' => FALSE,
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
      '#weight' => -70,
      '#eca_token_replacement' => TRUE,
    ];
    $this->buildYamlFormFields(
      $form,
      $this->t('Interpret above config value as YAML format'),
      $this->t('Nested data can be set using YAML format, for example <em>front: /node</em>. When using this format, this option needs to be enabled. When using tokens and YAML altogether, make sure that tokens are wrapped as a string. Example: <em>front: "[myurl:path]"</em>'),
      -60,
    );
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['output'] = $form_state->getValue('output');
    $this->configuration['use_yaml'] = !empty($form_state->getValue('use_yaml'));
    $this->configuration['validate_yaml'] = !empty($form_state->getValue('validate_yaml'));
    parent::submitConfigurationForm($form, $form_state);
  }

}
