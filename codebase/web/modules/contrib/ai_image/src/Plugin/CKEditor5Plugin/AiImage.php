<?php

declare(strict_types=1);

namespace Drupal\ai_image\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\editor\EditorInterface;

/**
 * CKEditor 5 OpenAI Completion plugin configuration.
 */
class AiImage extends CKEditor5PluginDefault implements CKEditor5PluginConfigurableInterface {

  use CKEditor5PluginConfigurableTrait;

  /**
   * The default configuration for this plugin.
   *
   * @var string[][]
   */
  const DEFAULT_CONFIGURATION = [
    'aiimage' => [
      'source' => 'openai',
      'openai_key' => '',
      'sd_key' => '',
      'prompt_extra' => 'hyper-realistic, super detailed',
    ],
  ];

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

    $form['aiimage']['source'] = [
      '#type' => 'select',
      '#title' => $this->t('AI engine'),
      '#options' => [
        'openai' => 'OpenAI',
        'sd' => 'Stable Diffusion',
      ],
      '#default_value' => $this->configuration['aiimage']['source'] ?? 'openai',
      '#description' => $this->t('Select which model to use to generate images.'),
    ];

    $form['aiimage']['openai_key'] = [
      '#type' => 'key_select',
      '#title' => $this->t('OpenAI secret key'),
      '#default_value' => $this->configuration['aiimage']['openai_key'],
    ];

    $form['aiimage']['sd_key'] = [
      '#type' => 'key_select',
      '#title' => $this->t('Stable Diffusion secret key'),
      '#default_value' => $this->configuration['aiimage']['sd_key'],
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
    $this->configuration['aiimage']['openai_key'] = $values['aiimage']['openai_key'];
    $this->configuration['aiimage']['sd_key'] = $values['aiimage']['sd_key'];
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
          'openai_key' => $config['aiimage']['openai_key'] ?? $options['aiimage']['openai_key'],
          'sd_key' => $config['aiimage']['sd_key'] ?? $options['aiimage']['sd_key'],
          'prompt_extra' => $config['aiimage']['prompt_extra'] ?? $options['aiimage']['prompt_extra'],
        ],
      ],
    ];
  }

}
