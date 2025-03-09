<?php

namespace Drupal\ai_provider_mistral\Plugin\AiProvider;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\Base\AiProviderClientBase;
use Drupal\ai\Enum\AiModelCapability;
use Drupal\ai\Exception\AiResponseErrorException;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatInterface;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai\OperationType\Embeddings\EmbeddingsInput;
use Drupal\ai\OperationType\Embeddings\EmbeddingsInterface;
use Drupal\ai\OperationType\Embeddings\EmbeddingsOutput;
use Drupal\ai\OperationType\Moderation\ModerationInput;
use Drupal\ai\OperationType\Moderation\ModerationInterface;
use Drupal\ai\OperationType\Moderation\ModerationOutput;
use Drupal\ai\OperationType\Moderation\ModerationResponse;
use Drupal\ai\Traits\OperationType\ChatTrait;
use Drupal\ai_provider_mistral\MistralChatMessageIterator;
use Drupal\ai_provider_mistral\MistralClient;
use OpenAI\Client;
use Symfony\Component\Yaml\Yaml;

/**
 * Plugin implementation of the 'mistral' provider.
 */
#[AiProvider(
  id: 'mistral',
  label: new TranslatableMarkup('Mistral AI'),
)]
class MistralProvider extends AiProviderClientBase implements
  ContainerFactoryPluginInterface,
  ChatInterface,
  EmbeddingsInterface,
  ModerationInterface {

  use ChatTrait;

  /**
   * The OpenAI Client for API calls.
   *
   * @var \OpenAI\Client|null
   */
  protected $client;

  /**
   * API Key.
   *
   * @var string
   */
  protected string $apiKey = '';

  /**
   * {@inheritdoc}
   */
  public function getConfiguredModels(?string $operation_type = NULL, array $capabilities = []): array {
    // No complex JSON support.
    if (in_array(AiModelCapability::ChatJsonOutput, $capabilities)) {
      return [];
    }
    set_error_handler([$this, 'errorCatcher'], E_ALL);
    $response = $this->getClient()->models()->list()->toArray();
    restore_error_handler();
    $models = [];
    if ($operation_type == 'chat') {
      if (isset($response['data'])) {
        foreach ($response['data'] as $model) {
          if (in_array(AiModelCapability::ChatWithImageVision, $capabilities) && str_starts_with($model['id'], 'pixtral')) {
            $models[$model['id']] = $model['id'];
          }
          if (empty($capabilities)) {
            $models[$model['id']] = $model['id'];
          }
        }
      }
    }
    elseif ($operation_type == 'embeddings') {
      $models['mistral-embed'] = 'mistral-embed';
    }
    elseif ($operation_type == 'moderation') {
      $models['mistral-moderation-latest'] = 'mistral-moderation-latest';
    }
    return $models;
  }

  /**
   * {@inheritdoc}
   */
  public function isUsable(?string $operation_type = NULL, array $capabilities = []): bool {
    // No vision support.
    if (in_array(AiModelCapability::ChatWithImageVision, $capabilities)) {
      return TRUE;
    }
    // If its not configured, it is not usable.
    if (!$this->getConfig()->get('api_key')) {
      return FALSE;
    }
    // If its one of the bundles that Mistral supports its usable.
    if ($operation_type) {
      return in_array($operation_type, $this->getSupportedOperationTypes());
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedOperationTypes(): array {
    return [
      'chat',
      'embeddings',
      'moderation',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(): ImmutableConfig {
    return $this->configFactory->get('ai_provider_mistral.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function getApiDefinition(): array {
    // Load the configuration.
    $definition = Yaml::parseFile($this->moduleHandler->getModule('ai_provider_mistral')->getPath() . '/definitions/api_defaults.yml');
    return $definition;
  }

  /**
   * {@inheritdoc}
   */
  public function getModelSettings(string $model_id, array $generalConfig = []): array {
    return $generalConfig;
  }

  /**
   * {@inheritdoc}
   */
  public function setAuthentication(mixed $authentication): void {
    // Set the new API key and reset the client.
    $this->apiKey = $authentication;
    $this->client = NULL;
  }

  /**
   * Gets the raw client.
   *
   * This is the client for inference.
   *
   * @return \OpenAI\Client
   *   The OpenAI client.
   */
  public function getClient(): Client {
    $this->loadClient();
    return $this->client;
  }

  /**
   * Get the custom client.
   *
   * @return \Drupal\ai_provider_mistral\MistralClient
   *   The custom client.
   */
  public function getCustomClient(): MistralClient {
    return new MistralClient($this->httpClient);
  }

  /**
   * Loads the Mistral Client with authentication if not initialized.
   */
  protected function loadClient(): void {
    if (!$this->client) {
      if (!$this->apiKey) {
        $this->setAuthentication($this->loadApiKey());
      }
      $this->client = \OpenAI::factory()
        ->withApiKey($this->apiKey)
        ->withBaseUri('https://api.mistral.ai/v1')
        ->withHttpClient($this->httpClient)
        ->make();
    }
  }

  /**
   * Loads and returns the custom client.
   *
   * @return \Drupal\ai_provider_mistral\MistralClient
   *   The custom client.
   */
  protected function loadCustomClient(): MistralClient {
    if (!$this->apiKey) {
      $this->setAuthentication($this->loadApiKey());
    }
    $client = $this->getCustomClient();
    $client->setApiToken($this->apiKey);
    return $client;
  }

  /**
   * {@inheritdoc}
   */
  public function chat(array|string|ChatInput $input, string $model_id, array $tags = []): ChatOutput {
    $this->loadClient();
    // Normalize the input if needed.
    $chat_input = $input;
    if ($input instanceof ChatInput) {
      $chat_input = [];
      // Add a system role if wanted.
      if ($this->chatSystemRole) {
        $chat_input[] = [
          'role' => 'system',
          'content' => $this->chatSystemRole,
        ];
      }
      foreach ($input->getMessages() as $message) {
        $content = [
          [
            'type' => 'text',
            'text' => $message->getText(),
          ],
        ];
        if (count($message->getImages())) {
          foreach ($message->getImages() as $image) {
            $content[] = [
              'type' => 'image_url',
              'image_url' => [
                'url' => $image->getAsBase64EncodedString(),
              ],
            ];
          }
        }
        $chat_input[] = [
          'role' => $message->getRole(),
          'content' => $content,
        ];
      }
    }
    $payload = [
      'model' => $model_id,
      'messages' => $chat_input,
    ] + $this->configuration;

    // This is ugly, but that is the only way we catch the array_map error
    // that happens when the array keys from Mistral are different then from
    // OpenAI.
    set_error_handler([$this, 'errorCatcher'], E_ALL);
    $response = $this->client->chat()->create($payload);
    restore_error_handler();

    if ($this->streamed) {
      $response = $this->client->chat()->createStreamed($payload);
      $message = new MistralChatMessageIterator($response);
    }
    else {
      $response = $this->client->chat()->create($payload)->toArray();
      $message = new ChatMessage($response['choices'][0]['message']['role'], $response['choices'][0]['message']['content']);
    }
    return new ChatOutput($message, $response, []);
  }

  /**
   * {@inheritdoc}
   */
  public function embeddings(string|EmbeddingsInput $input, string $model_id, array $tags = []): EmbeddingsOutput {
    $this->loadClient();
    // Normalize the input if needed.
    if ($input instanceof EmbeddingsInput) {
      $input = $input->getPrompt();
    }
    // Send the request.
    $payload = [
      'model' => $model_id,
      'input' => $input,
    ] + $this->configuration;
    $response = $this->client->embeddings()->create($payload)->toArray();

    return new EmbeddingsOutput($response['data'][0]['embedding'], $response, []);
  }

  /**
   * {@inheritdoc}
   */
  public function moderation(string|ModerationInput $input, ?string $model_id = NULL, array $tags = []): ModerationOutput {
    $client = $this->loadCustomClient();
    // Normalize the prompt if needed.
    if ($input instanceof ModerationInput) {
      $input = $input->getPrompt();
    }
    $payload = [
      'model' => $model_id ?? 'mistral-moderation-latest',
      'input' => $input,
    ] + $this->configuration;
    $response = $client->moderation($payload);

    $flagged = FALSE;
    foreach ($response['results'][0]['categories'] as $category_flagged) {
      if ($category_flagged) {
        $flagged = TRUE;
        break;
      }
    }
    $normalized = new ModerationResponse($flagged, $response['results'][0]['category_scores']);
    return new ModerationOutput($normalized, $response, []);
  }

  /**
   * Error catcher.
   */
  public function errorCatcher($errno, $errstr, $file, $line) {
    throw new AiResponseErrorException("Something undefined was broken in the response from Mistral AI: $errstr");
  }

  /**
   * {@inheritdoc}
   */
  public function maxEmbeddingsInput($model_id = ''): int {
    // @todo this is playing safe. Ideally, we should provide real number per model.
    return 1024;
  }

}
