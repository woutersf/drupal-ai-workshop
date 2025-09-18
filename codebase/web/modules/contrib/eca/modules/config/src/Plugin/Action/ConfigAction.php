<?php

namespace Drupal\eca_config\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\Action\ConfigActionManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca\Service\YamlParser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Action to read configuration.
 *
 * @Action(
 *   id = "eca_config_action",
 *   label = @Translation("Config Action"),
 *   description = @Translation("Execute a Drupal Core ConfigAction."),
 *   eca_version_introduced = "3.0.0"
 * )
 */
class ConfigAction extends ConfigurableActionBase {

  /**
   * The config action plugin manager.
   *
   * @var \Drupal\Core\Config\Action\ConfigActionManager
   */
  protected ConfigActionManager $configActionManager;

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
    $instance->configActionManager = $container->get('plugin.manager.config_action');
    $instance->yamlParser = $container->get('eca.service.yaml_parser');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE): AccessResultInterface|bool {
    $account = $account ?: $this->currentUser;
    if ($account->hasPermission('administer site configuration')) {
      return $return_as_object ? AccessResult::allowed()->cachePerPermissions() : TRUE;
    }
    return $return_as_object ? AccessResult::forbidden()->cachePerPermissions() : FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function execute(): void {
    try {
      $data = $this->yamlParser->parse($this->configuration['data']);
    }
    catch (ParseException) {
      $this->logger->error('Tried parsing data in action "eca_config_action", but parsing failed.');
      return;
    }
    $this->configActionManager->applyAction($this->configuration['action_id'], $this->configuration['config_name'], $data);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'action_id' => '',
      'config_name' => '',
      'data' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $actions = [];
    foreach ($this->configActionManager->getDefinitions() as $action_id => $definition) {
      if ($label = $definition['admin_label']) {
        $actions[$action_id] = (string) $label . ' (' . $action_id . ')';
      }
    }
    asort($actions);
    $form['action_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Config action'),
      '#description' => $this->t('The config action to be applied.'),
      '#default_value' => $this->configuration['action_id'],
      '#options' => $actions,
      '#required' => TRUE,
      '#weight' => -90,
    ];
    $form['config_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Config name'),
      '#description' => $this->t('The config name, for example <em>system.site</em>.'),
      '#default_value' => $this->configuration['config_name'],
      '#required' => TRUE,
      '#weight' => -80,
    ];
    $form['data'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Data'),
      '#description' => $this->t('The data for the config action, provided in YAML format.'),
      '#default_value' => $this->configuration['data'],
      '#weight' => -70,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['action_id'] = $form_state->getValue('action_id');
    $this->configuration['config_name'] = $form_state->getValue('config_name');
    $this->configuration['data'] = $form_state->getValue('data');
    parent::submitConfigurationForm($form, $form_state);
  }

}
