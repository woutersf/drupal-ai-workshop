<?php

namespace Drupal\ai_interpolator_fireworksai;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Fireworks AI API trait.
 */
trait FireworksaiApiTrait {

  /**
   * {@inheritDoc}
   */
  public function extraAdvancedFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    $form = parent::extraAdvancedFormFields($entity, $fieldDefinition);
    // Get the models.
    $form = $this->getModelsForForm($fieldDefinition, $entity);
    // Add common LLM parameters.
    $this->getGeneralHelper()->addCommonLlmParametersFormFields('firework_options', $form, $entity, $fieldDefinition);

    return $form;
  }

  /**
   * Get all models in Fireworksai for the form.
   *
   * @return array
   *   The response.
   */
  public function getModelsForForm(FieldDefinitionInterface $fieldDefinition, ContentEntityInterface $entity, $chat = TRUE) {
    $models = \Drupal::service('fireworksai.api')->getFireworkModels();
    $options = [];
    foreach ($models as $model) {
      if ($chat || $model['supports_chat']) {
        $extra = [];
        if ($model['supports_image_input']) {
          $extra[] = 'allows images';
        }
        $name = str_replace('accounts/fireworks/models/', '', $model['id']);
        if (!empty($extra)) {
          $name .= ' (' . implode(', ', $extra) . ')';
        }
        $options[$model['id']] = $name;
      }
    }

    $form['#attached']['library'][] = 'ai_interpolator_fireworksai/formSettings';

    $form['interpolator_firework_model'] = [
      '#type' => 'select',
      '#title' => $this->t('Fireworks AI Model'),
      '#options' => $options,
      '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_interpolator', 'interpolator_firework_model', ''),
    ];

    // Show the image injection if the model takes images.
    $options = ['' => 'No image field'] + $this->getGeneralHelper()->getFieldsOfType($entity, 'image');
    $form['interpolator_firework_image_field'] = [
      '#type' => 'select',
      '#options' => $options,
      '#prefix' => '<div id="firework-image-field">',
      '#suffix' => '</div>',
      '#title' => 'Image Reading Field',
      '#description' => $this->t('This model can ready images, choose the field to load the images from.'),
      '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_interpolator', 'interpolator_openai_vision_images', ''),
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function generateResponse($prompt, $interpolatorConfig, ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    $response = $this->fireworksaiChatResponse($prompt, $interpolatorConfig, $entity);
    return $response;
  }

  /**
   * {@inheritDoc}
   */
  public function generateRawResponse($prompt, $interpolatorConfig, ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    return $this->fireworksaiChatResponse($prompt, $interpolatorConfig, $entity, FALSE);
  }

  /**
   * Generate a call and response for a chat message.
   */
  public function fireworksaiChatResponse($prompt, $interpolatorConfig, $entity, $decode = TRUE) {
    $messages = [
      [
        'role' => 'user',
        'content' => $prompt,
      ],
    ];
    // If its with an image we do a little bit different.
    if ($interpolatorConfig['firework_image_field']) {
      $messages[0] = [
        'role' => 'user',
        'content' => [
          [
            'type' => 'text',
            'text' => $prompt,
          ],
        ],
      ];
      foreach ($entity->{$interpolatorConfig['firework_image_field']} as $imageEntity) {
        $messages[0]['content'][] = [
          'type' => 'image_url',
          'image_url' => [
            'url' => $this->getGeneralHelper()->base64EncodeFileEntity($imageEntity->entity),
          ],
        ];
      }
    }

    // Generate options.
    $options = [];
    foreach ($interpolatorConfig as $key => $value) {
      if (strpos($key, 'firework_options_') === 0) {
        if ($value) {
          $options[str_replace('firework_options_', '', $key)] = $value;
        }
      }
    }
    $response = json_decode(\Drupal::service('fireworksai.api')->chatCompletion($messages, $interpolatorConfig['firework_model'], $options), TRUE);
    if (isset($response['choices'][0]['message']['content'])) {
      return $decode ? $this->getGeneralHelper()->parseJson($response['choices'][0]['message']['content']) : $response['choices'][0]['message']['content'];
    }
    return NULL;
  }

}
