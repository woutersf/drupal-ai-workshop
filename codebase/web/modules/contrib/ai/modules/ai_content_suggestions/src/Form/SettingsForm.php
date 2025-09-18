<?php

declare(strict_types=1);

namespace Drupal\ai_content_suggestions\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ai_content_suggestions\AiContentSuggestionsPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Ai content suggestions settings for this site.
 */
final class SettingsForm extends ConfigFormBase {

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager.
   * @param \Drupal\ai_content_suggestions\AiContentSuggestionsPluginManager $pluginManager
   *   The AI Content Suggestions Plugin Manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    protected AiContentSuggestionsPluginManager $pluginManager,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('plugin.manager.ai_content_suggestions')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ai_content_suggestions_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['ai_content_suggestions.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $content_suggestions_config = $this->config('ai_content_suggestions.settings');
    $form['plugins'] = [
      '#type' => 'details',
      '#title' => $this->t('Settings for plugins'),
      '#tree' => TRUE,
      '#open' => TRUE,
    ];
    $form['plugins']['introduction'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Below is a list of the available plugins you can use to analyze your content.'),
    ];

    foreach ($this->pluginManager->getDefinitions() as $id => $config) {
      /** @var \Drupal\ai_content_suggestions\AiContentSuggestionsInterface $plugin */
      if ($plugin = $this->pluginManager->createInstance($id, $config)) {
        if ($plugin->isAvailable()) {
          $plugin->buildSettingsForm($form['plugins']);
        }
      }
    }

    $form['field_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Settings for per field suggestions'),
      '#tree' => TRUE,
    ];
    $form['field_settings']['field_widget_prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('System Prompt For Content Suggestions from Field Widget'),
      '#default_value' => $content_suggestions_config->get('field_widget_prompt') ?? '',
      '#description' => $this->t('This prompt will be used for all string/text field types if the AI Content Suggestions are enabled for the field in the widget settings of form display. Make sure that the parts with ```html  ``` are always in the prompt as some functionality depends on the response structure.'),
    ];

    // If new suggestion plugins are added, or new providers make existing
    // plugins available, we want to rebuild the form.
    $form['#cache']['contexts'][] = 'ai_content_suggestions_plugins';
    $form['#cache']['contexts'][] = 'ai_providers';

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = [];

    foreach ($this->pluginManager->getDefinitions() as $id => $definition) {
      /** @var \Drupal\ai_content_suggestions\AiContentSuggestionsInterface $plugin */
      if ($plugin = $this->pluginManager->createInstance($id, $definition)) {
        if ($plugin->isAvailable()) {
          $value = $form_state->getValue($id);
          // Ensure $value is an array before accessing keys.
          if (is_array($value) && !empty($value[$id . '_enabled'])) {
            $values[$id] = $value[$id . '_model'];
          }
          if (method_exists($plugin, 'saveSettingsForm')) {
            $plugin->saveSettingsForm($form, $form_state);
          }
        }
      }
    }

    $this->config('ai_content_suggestions.settings')
      ->set('field_widget_prompt', $form_state->getValue(['field_settings', 'field_widget_prompt']))
      ->set('plugins', $values)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
