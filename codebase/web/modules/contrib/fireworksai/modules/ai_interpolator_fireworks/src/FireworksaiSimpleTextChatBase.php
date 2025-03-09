<?php

namespace Drupal\ai_interpolator_fireworksai;

use Drupal\ai_interpolator\PluginBaseClasses\SimpleTextChat;
use Drupal\ai_interpolator\PluginInterfaces\AiInterpolatorFieldRuleInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\fireworksai\FireworksaiApi;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Helper class for simple text chat.
 */
class FireworksaiSimpleTextChatBase extends SimpleTextChat implements ContainerFactoryPluginInterface, AiInterpolatorFieldRuleInterface {

  // Load all the logic.
  use FireworksaiApiTrait;

  /**
   * {@inheritDoc}
   */
  public $title = 'Fireworks AI Simple Text Chat';

  /**
   * The Fireworksai API.
   */
  public FireworksaiApi $fireworksaiApi;

  /**
   * Constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FireworksaiApi $fireworksaiApi) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->fireworksaiApi = $fireworksaiApi;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('fireworksai.api')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function helpText() {
    return "This is for text chat without the Interpolator doing any additional processing. This is perfect for small models like Gemma.";
  }

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "Can you summarize the following for me.\n\nContext:\n{{ context }}";
  }

  /**
   * {@inheritDoc}
   */
  public function extraAdvancedFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    // Get the models.
    $form = $this->getModelsForForm($fieldDefinition, $entity);

    // Add common LLM parameters.
    $this->getGeneralHelper()->addCommonLlmParametersFormFields('firework', $form, $entity, $fieldDefinition);

    return $form;
  }

}
