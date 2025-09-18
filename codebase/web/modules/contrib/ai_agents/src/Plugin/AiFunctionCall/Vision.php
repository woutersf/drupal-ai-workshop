<?php

namespace Drupal\ai_agents\Plugin\AiFunctionCall;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai_agents\PluginInterfaces\AiAgentContextInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the vision function.
 */
#[FunctionCall(
  id: 'ai_agent:vision',
  function_name: 'ai_agents_vision',
  name: 'AI Vision',
  description: 'This method can take an image and a prompt and look and describe that image.',
  group: 'information_tools',
  module_dependencies: ['file'],
  context_definitions: [
    'prompt' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Prompt"),
      description: new TranslatableMarkup("The prompt of how to look at the image and how to describe it."),
      required: TRUE,
    ),
    'image_id' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup("Image ID"),
      description: new TranslatableMarkup("The image fid to describe."),
      required: FALSE,
    ),
  ],
)]
class Vision extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

  /**
   * The ai provider manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected AiProviderPluginManager $aiProviderManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Load from dependency injection container.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): FunctionCallInterface|static {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai.context_definition_normalizer'),
    );
    $instance->aiProviderManager = $container->get('ai.provider');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * The description.
   *
   * @var string
   */
  protected string $description = "";

  /**
   * {@inheritdoc}
   */
  public function execute() {
    // Collect the context values.
    $prompt = $this->getContextValue('prompt');
    $image_id = $this->getContextValue('image_id');
    $default = $this->aiProviderManager->getDefaultProviderForOperationType('chat_with_image_vision');
    // If no default provider is set, we cannot proceed.
    if (!$default) {
      $this->description = "No AI provider is configured for the vision operation.";
      return;
    }
    $provider = $this->aiProviderManager->createInstance($default['provider_id']);
    $prompt = "Could you describe this image?\n";
    if ($prompt) {
      $prompt .= "Take the following into account: " . $prompt;
    }
    $file = $this->entityTypeManager->getStorage('file')->load($image_id);
    // Make sure it exists and that the user has access to the file.
    if (!$file || !$file->access('view')) {
      $this->description = "The image could not be found.";
      return;
    }
    $image = new ImageFile();
    $image->setFileFromFile($file);
    $images = [$image];
    $input = new ChatInput([
      new ChatMessage('user', $prompt, $images),
    ]);
    $response = $provider->chat($input, $default['model_id'], [
      'vision_tool',
    ]);
    $this->description = $response->getNormalized()->getText();
  }

  /**
   * {@inheritdoc}
   */
  public function getReadableOutput(): string {
    return $this->description;
  }

}
