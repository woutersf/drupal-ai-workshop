<?php

namespace Drupal\admin_toolbar_tools\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for AdminToolbar Tools.
 *
 * @package Drupal\admin_toolbar_tools\Form
 */
class AdminToolbarToolsSettingsForm extends ConfigFormBase {

  /**
   * The cache menu instance.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheMenu;

  /**
   * The menu link manager instance.
   *
   * @var \Drupal\Core\Menu\MenuLinkManagerInterface
   */
  protected $menuLinkManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->cacheMenu = $container->get('cache.menu');
    $instance->menuLinkManager = $container->get('plugin.manager.menu.link');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'admin_toolbar_tools.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'admin_toolbar_tools_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('admin_toolbar_tools.settings');
    $form['max_bundle_number'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum number of bundle sub-menus to display'),
      '#description' => $this->t('Loading a large number of items can cause performance issues.'),
      '#default_value' => $config->get('max_bundle_number'),
    ];

    $form['hoverintent_functionality'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable/Disable the hoverintent functionality'),
      '#description' => $this->t('Check it if you want to enable the hoverintent feature.'),
      '#default_value' => $config->get('hoverintent_functionality'),
    ];

    $form['show_local_tasks'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable/Disable local tasks display'),
      '#description' => $this->t('Local tasks such as node edit and delete.'),
      '#default_value' => $config->get('show_local_tasks'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('admin_toolbar_tools.settings')
      ->set('max_bundle_number', $form_state->getValue('max_bundle_number'))
      ->set('hoverintent_functionality', $form_state->getValue('hoverintent_functionality'))
      ->set('show_local_tasks', $form_state->getValue('show_local_tasks'))
      ->save();
    parent::submitForm($form, $form_state);
    $this->cacheMenu->invalidateAll();
    $this->menuLinkManager->rebuild();
  }

}
