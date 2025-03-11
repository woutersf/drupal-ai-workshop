<?php

declare(strict_types=1);

namespace Drupal\ai_image\Plugin\CKEditor5Plugin;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\editor\EditorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * CKEditor 5 OpenAI Completion plugin configuration.
 */
class AiImage extends CKEditor5PluginDefault implements CKEditor5PluginConfigurableInterface, ContainerFactoryPluginInterface {

  use CKEditor5PluginConfigurableTrait;

  /**
   * The provider plugin manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected AiProviderPluginManager $aiProviderManager;


  /**
   * The default configuration for this plugin.
   *
   * @var string[][]
   */
  const DEFAULT_CONFIGURATION = [
    'aiimage' => [
      'source' => 'openai',
      'prompt_extra' => 'hyper-realistic, super detailed',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AiProviderPluginManager $ai_provider_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setConfiguration($configuration);
    $this->aiProviderManager = $ai_provider_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai.provider'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return static::DEFAULT_CONFIGURATION;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $form['aiimage'] = [
      '#title' => $this->t('AI Image'),
      '#type' => 'details',
      '#description' => $this->t('The following setting controls the behavior of the image generation actions in CKEditor.'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];

    $options = $this->aiProviderManager->getSimpleProviderModelOptions('text_to_image');
    array_shift($options);
    array_splice($options, 0, 1);
    $form['aiimage']['source'] = [
      '#type' => 'select',
      '#title' => $this->t('AI generation model'),
      '#options' => $options,
      "#empty_option" => $this->t('-- Default from AI module (text_to_image) --'),
      '#default_value' => $this->configuration['aiimage']['source'] ?? $this->aiProviderManager->getSimpleDefaultProviderOptions('text_to_image'),
      '#description' => $this->t('Select which generation model to use for this plugin. See the <a href=":link">Provider overview</a> for details about each provider.', [':link' => '/admin/config/ai/providers']),
    ];

    $form['aiimage']['prompt_extra'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Prompt extra'),
      '#description' => $this->t('This string will be added to every prompt'),
      '#default_value' => $this->configuration['aiimage']['prompt_extra'] ?? 'hyper-realistic, super detailed',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->configuration['aiimage']['source'] = $values['aiimage']['source'];
    $this->configuration['aiimage']['prompt_extra'] = $values['aiimage']['prompt_extra'];
    _ai_image_check_default_provider_and_model();
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $options = $static_plugin_config;
    $config = $this->getConfiguration();

    return [
      'ai_image_aiimg' => [
        'aiimage' => [
          'source' => $config['aiimage']['source'] ?? $options['aiimage']['source'],
          'prompt_extra' => $config['aiimage']['prompt_extra'] ?? $options['aiimage']['prompt_extra'],
        ],
      ],
    ];
  }

}
