<?php

namespace Drupal\search_api_ai_fireworks\Plugin\EmbeddingEngine;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\fireworksai\FireworksaiApi;
use Drupal\search_api_ai\Attribute\EmbeddingEngine;
use Drupal\search_api_ai\EmbeddingEngineInterface;

/**
 * The Fireworks Nomic Embed Text embedding engine.
 */
#[EmbeddingEngine(
  id: 'fireworks_nomic_embed_text_1',
  label: new TranslatableMarkup('Fireworks Nomic Embed Text 1.0'),
  description: new TranslatableMarkup('Fireworks Nomic Embed Text 1.0.'),
  dimension: 768,
)]
final class NomicEmbedText1 implements EmbeddingEngineInterface {

  use StringTranslationTrait;

  /**
   * The Fireworksai client.
   *
   * @var \Fireworksai\FireworksaiApi
   */
  private FireworksaiApi $client;

  /**
   * The configuration.
   *
   * @var array
   */
  private array $config;

  /**
   * Constructs a new Fireworks instance.
   */
  public function __construct(array $config) {
    $this->config = $config;
    $this->client = \Drupal::service('fireworksai.api');
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailable(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildEmbeddingConfigurationForm(): array {
    $form['dimension'] = [
      '#type' => 'number',
      '#default_value' => 768,
      '#required' => TRUE,
      '#title' => $this->t('Dimension'),
      '#description' => $this->t('The dimension of the embeddings. Can be under 768.'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function generateEmbeddings(string $text, array $options = []): array {
    $options = [];
    if ($this->config['dimension']) {
      $options['dimension'] = $this->config['dimension'];
    }
    $response = json_decode($this->client->embeddingsCreate('nomic-ai/nomic-embed-text-v1', $text, $options), TRUE);
    $query_embedding = $response['data'][0]['embedding'] ?? NULL;
    return $query_embedding;
  }

  /**
   * {@inheritdoc}
   */
  public function getDimension(): int {
    return $this->config['dimension'] ?? $this->getPluginDefinition()['dimension'];
  }

}
