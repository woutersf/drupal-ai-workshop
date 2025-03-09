<?php

namespace Drupal\ai_interpolator_fireworksai\Plugin\AiInterPolatorFieldRules;

use Drupal\ai_interpolator\PluginBaseClasses\TextToImage;
use Drupal\ai_interpolator\PluginInterfaces\AiInterpolatorFieldRuleInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * The rules for a text generation.
 *
 * @AiInterpolatorFieldRule(
 *   id = "ai_interpolator_fireworksai_text_to_image",
 *   title = @Translation("Fireworks AI Text To Image"),
 *   field_rule = "image",
 *   target = "file"
 * )
 */
class FireworksaiTextToImage extends TextToImage implements AiInterpolatorFieldRuleInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'Fireworks AI Text To Image';

  /**
   * The models and options.
   *
   * @var array
   */
  public $models = [
    'stable-diffusion-xl-1024-v1-0' => [
      'account' => 'fireworks',
      'name' => 'Stable Diffusion XL v1.0',
    ],
    'playground-v2-1024px-aesthetic' => [
      'account' => 'fireworks',
      'name' => 'Playground v2.0',
    ],
    'playground-v2-5-1024px-aesthetic' => [
      'account' => 'fireworks',
      'name' => 'Playground v2.5',
    ],
    'SSD-1B' => [
      'account' => 'fireworks',
      'name' => 'Segmind Stable Diffusion 1B',
    ],
    'japanese-stable-diffusion-xl' => [
      'account' => 'fireworks',
      'name' => 'Japanese Stable Diffusion XL',
    ],
  ];

  /**
   * {@inheritDoc}
   */
  public function extraAdvancedFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    $form = [];

    $models = [];
    foreach ($this->models as $id => $model) {
      $models[$id] = $model['name'];
    }
    $form['interpolator_firework_model'] = [
      '#type' => 'select',
      '#title' => $this->t('Image Model'),
      '#options' => $models,
      '#description' => $this->t('Choose the image model that you want to use.'),
    ];

    $form['interpolator_firework_resolution'] = [
      '#type' => 'select',
      '#title' => $this->t('Image Resolution'),
      '#options' => [
        '1536x640' => $this->t('1536x640'),
        '1344x768' => $this->t('1344x768'),
        '1216x832' => $this->t('1216x832'),
        '1152x896' => $this->t('1152x896'),
        '1024x1024' => $this->t('1024x1024'),
        '896x1152' => $this->t('896x1152'),
        '832x1216' => $this->t('832x1216'),
        '768x1344' => $this->t('768x1344'),
        '640x1536' => $this->t('640x1536'),
      ],
      '#description' => $this->t('Choose the image resolution that you want to produce.'),
    ];

    $form['interpolator_firework_options_negative_prompt'] = [
      '#type' => 'textarea',
      '#title' => 'Negative Prompt',
      '#attributes' => [
        'placeholder' => 'Ugly images, Wordpress logo',
      ],
      '#description' => $this->t('The negative prompt to send.'),
      '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_interpolator', 'interpolator_firework_options_negative_prompt', ''),
    ];

    $this->getGeneralHelper()->addTokenConfigurationFormField('interpolator_firework_options_negative_prompt', $form, $entity, $fieldDefinition);

    $form['interpolator_firework_image_type'] = [
      '#type' => 'select',
      '#title' => 'Image Type',
      '#options' => [
        'image/png' => 'PNG',
        'image/jpeg' => 'JPG',
      ],
      '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_interpolator', 'interpolator_firework_image_type', 'image/png'),
    ];

    $form['interpolator_firework_options_cfg_scale'] = [
      '#type' => 'number',
      '#title' => 'Scale',
      '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_interpolator', 'interpolator_firework_options_cfg_scale', 7),
      '#description' => $this->t('The scale of the image.'),
      '#min' => 0,
      '#max' => 100,
    ];

    $form['interpolator_firework_options_steps'] = [
      '#type' => 'number',
      '#title' => 'Scale',
      '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_interpolator', 'interpolator_firework_options_steps', 30),
      '#description' => $this->t('The steps of the image.'),
      '#min' => 0,
      '#max' => 100,
    ];

    return $form;
  }

  /**
   * Mockup for generating response, have to be filled in by the rule.
   *
   * @param string $prompt
   *   The prompt.
   * @param array $interpolatorConfig
   *   The configuration.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition.
   *
   * @return mixed
   *   The response.
   */
  public function generateResponse($prompt, $interpolatorConfig, ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    // Fix negative prompt if gotten from token.
    $interpolatorConfig['firework_options_negative_prompt'] = $this->getGeneralHelper()->getConfigValue('firework_options_negative_prompt', $interpolatorConfig, $entity);
    unset($interpolatorConfig['firework_options_negative_prompt_token']);
    $options = [];
    foreach ($interpolatorConfig as $key => $value) {
      if (strpos($key, 'firework_options_') === 0) {
        if ($value) {
          $options[str_replace('firework_options_', '', $key)] = $value;
        }
      }
    }

    // Get height and width.
    $parts = explode('x', $interpolatorConfig['firework_resolution']);
    $response = \Drupal::service('fireworksai.api')->textToImage($prompt, $interpolatorConfig['firework_model'], $interpolatorConfig['firework_image_type'], $parts[0], $parts[1], $options);
    return $response->getContents();
  }

  /**
   * Gets the filename. Override this.
   *
   * @param array $args
   *   If arguments are needed to create the filename.
   *
   * @return string
   *   The filename.
   */
  public function getFileName(array $args = []) {
    $extension = $args['firework_image_type'] == 'image/jpeg' ? 'jpg' : 'png';
    return 'fireworks_image.' . $extension;
  }

}
