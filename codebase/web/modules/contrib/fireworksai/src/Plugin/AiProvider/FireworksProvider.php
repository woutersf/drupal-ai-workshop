<?php

namespace Drupal\fireworksai\Plugin\AiProvider;

use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\Base\AiProviderClientBase;
use Drupal\ai\Enum\AiModelCapability;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatInterface;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai\OperationType\Embeddings\EmbeddingsInput;
use Drupal\ai\OperationType\Embeddings\EmbeddingsInterface;
use Drupal\ai\OperationType\Embeddings\EmbeddingsOutput;
use Drupal\ai\OperationType\TextToImage\TextToImageInput;
use Drupal\ai\OperationType\TextToImage\TextToImageInterface;
use Drupal\ai\OperationType\TextToImage\TextToImageOutput;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\fireworksai\FireworksaiApi;
use Symfony\Component\Yaml\Yaml;

/**
 * Plugin implementation of the 'fireworks' provider.
 */
#[AiProvider(
  id: 'fireworks',
  label: new TranslatableMarkup('Fireworks AI'),
)]
class FireworksProvider extends AiProviderClientBase implements
  ChatInterface,
  EmbeddingsInterface,
  TextToImageInterface {

  /**
   * The Fireworks Client.
   *
   * @var \Drupal\fireworksai\FireworksaiApi
   */
  protected $client;

  /**
   * API Key.
   *
   * @var string
   */
  protected string $apiKey = '';

  /**
   * Run moderation call, before a normal call.
   *
   * @var bool|null
   */
  protected bool|null $moderation = NULL;

  /**
   * {@inheritdoc}
   */
  public function getConfiguredModels(string $operation_type = NULL, array $capabilities = []): array {
    $this->loadClient();
    return $this->getModels($operation_type, $capabilities);
  }

  /**
   * {@inheritdoc}
   */
  public function isUsable(string $operation_type = NULL, array $capabilities = []): bool {
    // If its not configured, it is not usable.
    if (!$this->getConfig()->get('api_key')) {
      return FALSE;
    }
    // If its one of the bundles that Fireworks supports its usable.
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
      'text_to_image',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(): ImmutableConfig {
    return $this->configFactory->get('fireworksai.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function getApiDefinition(): array {
    // Load the configuration.
    $definition = Yaml::parseFile($this->moduleHandler->getModule('fireworksai')->getPath() . '/definitions/api_defaults.yml');
    return $definition;
  }

  /**
   * {@inheritdoc}
   */
  public function getModelSettings(string $model_id, array $generalConfig = []): array {
    if ($model_id == 'thenlper/gte-large' || $model_id == 'WhereIsAI/UAE-Large-V1') {
      $generalConfig['dimensions']['default'] = 1024;
    }
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
   * @param string $api_key
   *   If the API key should be hot swapped.
   *
   * @return \Drupal\fireworksai\FireworksaiApi
   *   The Fireworks AI client.
   */
  public function getClient(string $api_key = ''): FireworksaiApi {
    if ($api_key) {
      $this->setAuthentication($api_key);
    }
    $this->loadClient();
    return $this->client;
  }

  /**
   * Loads the Fireworks Client with authentication if not initialized.
   */
  protected function loadClient(): void {
    if (!$this->client) {
      if (!$this->apiKey) {
        $this->setAuthentication($this->loadApiKey());
      }
      $this->client = \Drupal::service('fireworksai.api');
      $this->client->setApiKey($this->apiKey);
    }
  }

  /**
   * Load API key from key module.
   *
   * @return string
   *   The API key.
   */
  protected function loadApiKey(): string {
    if ($this->keyRepository->getKey($this->getConfig()->get('api_key'))) {
      return $this->keyRepository->getKey($this->getConfig()->get('api_key'))->getKeyValue();
    }
    return $this->getConfig()->get('api_key');
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
    $response = json_decode($this->client->chatCompletion($chat_input, $model_id, $this->configuration), TRUE);

    $message = new ChatMessage($response['choices'][0]['message']['role'], $response['choices'][0]['message']['content']);
    return new ChatOutput($message, $response, []);
  }

  /**
   * {@inheritdoc}
   */
  public function textToImage(string|TextToImageInput $input, string $model_id, array $tags = []): TextToImageOutput {
    $this->loadClient();
    // Normalize the input if needed.
    if ($input instanceof TextToImageInput) {
      $input = $input->getText();
    }
    // The send.
    $image_type = $this->configuration['accept'];
    [$width, $height] = explode('x', $this->configuration['image_size']);
    unset($this->configuration['accept']);
    unset($this->configuration['image_size']);
    $response = $this->client->textToImage($input, $model_id, $image_type, $width, $height, $this->configuration);

    $images[] = $response;
    return new TextToImageOutput($images, $response, []);
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
    $response = json_decode($this->client->embeddingsCreate($model_id, $input, $this->configuration), TRUE);

    return new EmbeddingsOutput($response['data'][0]['embedding'], $response, []);
  }

  /**
   * {@inheritdoc}
   */
  public function maxEmbeddingsInput($model_id = ''): int {
    return 1024;
  }

  /**
   * Obtains a list of models from Fireworks and caches the result.
   *   *
   * @param string $operation_type
   *   The bundle to filter models by.
   *
   * @return array
   *   A filtered list of public models.
   */
  public function getModels(string $operation_type, $capabilities): array {
    $models = [];

    $cache_data = $this->cacheBackend->get('fireworks_models_' . $operation_type, $models);

    if (!empty($cache_data)) {
      //return $cache_data->data;
    }

    $list = $this->client->getFireworkModels();

    foreach ($list as $model) {
      if ($operation_type == 'chat' && $model['supports_chat']) {
        // Only vision models.
        if (in_array(AiModelCapability::ChatWithImageVision, $capabilities) && !$model['supports_image_input']) {
          continue;
        }
        $models[$model['id']] = str_replace('accounts/fireworks/models/', '', $model['id']);
      }
    }

    if ($operation_type == 'text_to_image') {
      $models['stable-diffusion-xl-1024-v1-0'] = 'Stable Diffusion XL v1.0';
      $models['playground-v2-1024px-aesthetic'] = 'Playground v2.0';
      $models['playground-v2-5-1024px-aesthetic'] = 'Playground v2.5';
      $models['SSD-1B'] = 'Segmind Stable Diffusion 1B';
      $models['japanese-stable-diffusion-xl'] = 'Japanese Stable Diffusion XL';
    }

    if ($operation_type == 'embeddings') {
      $models['nomic-ai/nomic-embed-text-v1.5'] = 'Nomic Embed Text v1.5';
      $models['nomic-ai/nomic-embed-text-v1'] = 'Nomic Embed Text v1';
      $models['WhereIsAI/UAE-Large-V1'] = 'UAE Large V1';
      $models['thenlper/gte-large'] = 'GTE Large';
      $models['thenlper/gte-base'] = 'GTE Base';
    }

    if (!empty($models)) {
      asort($models);
      $this->cacheBackend->set('fireworks_models_' . $operation_type, $models);
    }

    return $models;
  }

}
