<?php

declare(strict_types=1);

namespace Drupal\ai_image\Plugin\CKEditor5Plugin;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\editor\EditorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * CKEditor 5 OpenAI Completion plugin configuration.
 */
class AiImage extends CKEditor5PluginDefault implements ContainerFactoryPluginInterface, CKEditor5PluginConfigurableInterface {

  use CKEditor5PluginConfigurableTrait;

  /**
   * The AI Provider service.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $providerManager;


  /**
   * The default configuration for this plugin.
   *
   * @var string[][]
   */
  const DEFAULT_CONFIGURATION = [
    'aiimage' => [
      'source' => '000-AI-IMAGE-DEFAULT',
      'prompt_extra' => 'hyper-realistic, super detailed',
    ],
  ];

  public function __construct(array                     $configuration,
                              string                    $plugin_id,
                              CKEditor5PluginDefinition $plugin_definition,
                              AiProviderPluginManager   $provider_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->providerManager = $provider_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container,
                                array              $configuration,
                                                   $plugin_id,
                                                   $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai.provider'));
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

    $providers = [];
    $options['000-AI-IMAGE-DEFAULT'] = 'Default provider (configured in AI default settings)';
    foreach ($this->providerManager->getDefinitions() as $id => $definition) {
      $providers[$id] = $this->providerManager->createInstance($id);
    }

    foreach ($providers as $provider) {
      if ($provider->isUsable('text_to_image')) {
        $options[$provider->getPluginId()] = $provider->getPluginDefinition()['label'];
      }
    }

    $form['aiimage']['source'] = [
      '#type' => 'select',
      '#title' => $this->t('AI engine'),
      '#options' => $options,
      '#default_value' => $this->configuration['aiimage']['source'] ?? '000-AI-IMAGE-DEFAULT',
      '#description' => $this->t('Select which model to use to generate images.'),
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
    if ('000-AI-IMAGE-DEFAULT' == $this->configuration['aiimage']['source']) {
      // Make sure a default is selected.
      _ai_image_check_default_provider_and_model();
    }
    $this->configuration['aiimage']['prompt_extra'] = $values['aiimage']['prompt_extra'];
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
