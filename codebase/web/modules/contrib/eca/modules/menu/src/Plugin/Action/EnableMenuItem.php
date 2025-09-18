<?php

namespace Drupal\eca_menu\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Enables a specified menu item.
 *
 * @Action(
 *   id = "eca_menu_enable_menu_item",
 *   label = @Translation("Menu Item: Enable or Disable"),
 *   description = @Translation("Enable or disable a menu link (can be module provided)."),
 *   eca_version_introduced = "2.1.x",
 *   type = "entity"
 * )
 */
class EnableMenuItem extends ConfigurableActionBase {

  /**
   * The MenuLinkManager.
   *
   * @var \Drupal\Core\Menu\MenuLinkManagerInterface
   */
  protected MenuLinkManagerInterface $menuLinkManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->menuLinkManager = $container->get('plugin.manager.menu.link');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'menu_item' => '',
      'enabled' => TRUE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['menu_item'] = [
      '#type' => 'textfield',
      '#title' => $this->t('ID of menu item'),
      '#default_value' => $this->configuration['menu_item'],
      '#description' => $this->t('The id of a menu item provided by code (e.g. not a menu_link_content created in the menu UI).'),
      '#eca_token_replacement' => TRUE,
      '#required' => TRUE,
    ];
    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable'),
      '#description' => $this->t('Select to have the menu item enabled, or leave unchecked to have it disabled.'),
      '#default_value' => $this->configuration['enabled'],
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['menu_item'] = $form_state->getValue('menu_item');
    $this->configuration['enabled'] = !empty($form_state->getValue('enabled'));
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $entity = NULL): void {
    $menuItemId = (string) $this->tokenService->replace($this->configuration['menu_item']);
    $definition = $this->menuLinkManager->getDefinition($menuItemId);
    // Set the 'enabled' value based on the configuration, and save.
    $definition['enabled'] = ($this->configuration['enabled']) ? 1 : 0;
    $this->menuLinkManager->updateDefinition($menuItemId, $definition);
  }

}
