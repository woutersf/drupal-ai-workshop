<?php

namespace Drupal\ai_provider_litellm\Plugin\AiProvider;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\OperationType\Embeddings\EmbeddingsInput;
use Drupal\ai_provider_litellm\LiteLLM\LiteLlmAiClient;
use Drupal\ai_provider_openai\Plugin\AiProvider\OpenAiProvider;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'LiteLLM Proxy' provider.
 */
#[AiProvider(
  id: 'litellm',
  label: new TranslatableMarkup('LiteLLM Proxy'),
)]
class LiteLlmAiProvider extends OpenAiProvider {

  /**
   * The LiteLLM API client.
   *
   * @var \Drupal\ai_provider_litellm\LiteLLM\LiteLlmAiClient
   */
  protected LiteLlmAiClient $liteLlmClient;

  /**
   * The AI cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected CacheBackendInterface $aiCache;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $parent_instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $parent_instance->aiCache = $container->get('cache.ai');
    return $parent_instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function loadClient(): void {
    parent::loadClient();
    $config = $this->getConfig();
    $this->liteLlmClient = new LiteLlmAiClient(
      $this->httpClient,
      $this->keyRepository,
      $config->get('host'),
      $config->get('api_key'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(): ImmutableConfig {
    return $this->configFactory->get('ai_provider_litellm.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function getModels(string $operation_type, $capabilities): array {
    $models = [];
    foreach ($this->liteLlmClient->models() as $model) {
      switch ($operation_type) {
        case 'text_to_image':
          if ($model->supportsImageOutput) {
            $models[$model->name] = $model->name;
          }
          break;

        case 'text_to_speech':
          if ($model->supportsAudioOutput) {
            $models[$model->name] = $model->name;
          }
          break;

        case 'audio_to_audio':
          if ($model->supportsAudioInput && $model->supportsAudioOutput) {
            $models[$model->name] = $model->name;
          }
          break;

        case 'moderation':
          if ($model->supportsModeration) {
            $models[$model->name] = $model->name;
          }
          break;

        case 'embeddings':
          if ($model->supportsEmbeddings) {
            $models[$model->name] = $model->name;
          }
          break;

        case 'chat':
          if ($model->supportsChat) {
            $models[$model->name] = $model->name;
          }
          break;

        case 'image_and_audio_to_video':
          if ($model->supportsImageAndAudioToVideo) {
            $models[$model->name] = $model->name;
          }
          break;

        default:
          break;
      }
    }
    return $models;
  }

  /**
   * {@inheritdoc}
   */
  public function getSetupData(): array {
    // Don't set up any default models.
    return [
      'key_config_name' => 'api_key',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function postSetup(): void {
    // Prevent the OpenAI rate limit check.
  }

  /**
   * {@inheritdoc}
   */
  public function embeddingsVectorSize(string $model_id): int {
    // Since we don't have the size, we need to calculate it.
    $cid = 'embeddings_size:' . $this->getPluginId() . ':' . $model_id;
    if ($cached = $this->aiCache->get($cid)) {
      return $cached->data;
    }

    // Just until all providers have the trait.
    if (!method_exists($this, 'embeddings')) {
      return 0;
    }
    // Normalize the input.
    $input = new EmbeddingsInput('Hello world!');
    $embedding = $this->embeddings($input, $model_id);
    try {
      $size = count($embedding->getNormalized());
    }
    catch (\Exception $e) {
      return 0;
    }
    $this->aiCache->set($cid, $size);

    return $size;
  }

  /**
   * {@inheritdoc}
   */
  public function getModelSettings(string $model_id, array $generalConfig = []): array {
    $this->loadClient();
    $model_info = $this->liteLlmClient->models()[$model_id] ?? NULL;

    if (!$model_info) {
      return $generalConfig;
    }

    foreach (array_keys($generalConfig) as $name) {
      if (!in_array($name, $model_info->supportedOpenAiParams)) {
        unset($generalConfig[$name]);
      }
    }

    return $generalConfig;
  }

}
