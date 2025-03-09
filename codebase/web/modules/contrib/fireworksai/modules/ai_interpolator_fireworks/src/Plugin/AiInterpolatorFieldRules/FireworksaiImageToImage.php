<?php

namespace Drupal\ai_interpolator_fireworksai\Plugin\AiInterPolatorFieldRules;

use Drupal\ai_interpolator\PluginBaseClasses\TextToImage;
use Drupal\ai_interpolator\PluginInterfaces\AiInterpolatorFieldRuleInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * The rules for a image to image manipulation.
 *
 * @AiInterpolatorFieldRule(
 *   id = "ai_interpolator_fireworksai_image_to_image",
 *   title = @Translation("Fireworks AI Image To Image"),
 *   field_rule = "image",
 *   target = "file"
 * )
 */
class FireworksaiImageToImage extends TextToImage implements AiInterpolatorFieldRuleInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'Fireworks AI Image To Image';

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

    $form['interpolator_firework_options_negative_prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Negative Prompt'),
      '#attributes' => [
        'placeholder' => 'Ugly images, Wordpress logo',
      ],
      '#description' => $this->t('The negative prompt to send.'),
      '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_interpolator', 'interpolator_firework_options_negative_prompt', ''),
    ];

    $form['interpolator_firework_input_image'] = [
      '#type' => 'select',
      '#options' => $this->getGeneralHelper()->getFieldsOfType($entity, 'image'),
      '#title' => 'Input Image',
      '#description' => $this->t('The image field to use as input image.'),
      '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_interpolator', 'interpolator_firework_input_image', ''),
    ];

    $this->getGeneralHelper()->addTokenConfigurationFormField('interpolator_firework_options_negative_prompt', $form, $entity, $fieldDefinition);

    $form['interpolator_firework_image_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Image Type'),
      '#options' => [
        'image/png' => 'PNG',
        'image/jpeg' => 'JPG',
      ],
      '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_interpolator', 'interpolator_firework_image_type', 'image/png'),
    ];

    $form['interpolator_firework_options_init_image_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Init Image Mode'),
      '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_interpolator', 'interpolator_firework_options_init_image_mode', ''),
      '#options' => [
        '' => $this->t('None'),
        'IMAGE_STRENGTH' => $this->t('Image Strength'),
        'STEP_SCHEDULE' => $this->t('Step Schedule'),
      ],
    ];

    $form['interpolator_firework_options_image_strength'] = [
      '#type' => 'number',
      '#title' => $this->t('Image strength'),
      '#min' => 0,
      '#max' => 1,
      '#step' => '0.01',
      '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_interpolator', 'interpolator_firework_options_image_strength', '0.5'),
    ];

    $form['interpolator_firework_options_cfg_scale'] = [
      '#type' => 'number',
      '#title' => $this->t('Scale'),
      '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_interpolator', 'interpolator_firework_options_cfg_scale', 7),
      '#description' => $this->t('The scale of the image.'),
      '#min' => 0,
      '#max' => 100,
    ];

    $form['interpolator_firework_options_steps'] = [
      '#type' => 'number',
      '#title' => $this->t('Steps'),
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

    $inputImage = $entity->{$interpolatorConfig['firework_input_image']}->entity;

    $response = \Drupal::service('fireworksai.api')->imageToImage($prompt, $interpolatorConfig['firework_model'], $interpolatorConfig['firework_image_type'], $inputImage, $options);
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
