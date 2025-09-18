<?php

namespace Drupal\ai_provider_litellm\LiteLLM;

use Drupal\ai_provider_litellm\DTO\Model;
use Drupal\key\KeyRepositoryInterface;
use GuzzleHttp\RequestOptions;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Integration with the LiteLLM API.
 */
class LiteLlmAiClient {

  /**
   * Construct the LiteLlmAiClient.
   *
   * @param \GuzzleHttp\Client $client
   *   The HTTP client for making requests.
   * @param \Drupal\key\KeyRepositoryInterface $keyRepository
   *   The key repository service.
   * @param string $host
   *   The LiteLLM host URL.
   * @param string $apiKey
   *   The API key name, or an API key itself.
   */
  public function __construct(
    protected ClientInterface $client,
    protected KeyRepositoryInterface $keyRepository,
    protected string $host,
    protected string $apiKey,
  ) {}

  /**
   * Get the information from LiteLLM about a specific model.
   *
   * @param string $id
   *   The model to get information for.
   *
   * @return \Drupal\ai_provider_litellm\DTO\Model
   *   The model information.
   */
  public function model(string $id): Model {
    $response = $this->getRequest($this->host . '/model/info');
    $decoded_response = json_decode($response->getBody()->getContents());

    return Model::createFromResponse($decoded_response->data);
  }

  /**
   * Get available models.
   *
   * @return \Drupal\ai_provider_litellm\DTO\Model[]
   *   The available models.
   */
  public function models(): array {
    $response = $this->getRequest($this->host . '/model/info');
    $decoded_response = json_decode($response->getBody()->getContents());

    $models = [];
    foreach ($decoded_response->data as $model_info) {
      $models[$model_info->model_name] = Model::createFromResponse($model_info);
    }

    return $models;
  }

  /**
   * Get key information.
   *
   * @return array<string, array<string, mixed>>
   *   The key information.
   */
  public function keyInfo(): array {
    $response = $this->getRequest($this->host . '/key/info');
    $decoded_response = json_decode($response->getBody()->getContents());

    $keys = [];
    $keys[$decoded_response->key] = $decoded_response;

    return $keys;
  }

  /**
   * Make a GET request to LiteLLM.
   *
   * @param string $uri
   *   The URI to request.
   * @param array $headers
   *   Any additional headers to include. The x-goog-api-key header will be
   *   added automatically.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The response from the LiteLLM API.
   */
  protected function getRequest(string $uri, array $headers = []): ResponseInterface {
    $api_key = $this->keyRepository->getKey($this->apiKey) ? $this->keyRepository->getKey($this->apiKey)->getKeyValue() : $this->apiKey;
    return $this->client->get(
      $uri,
      [
        RequestOptions::HEADERS => ['Authorization' => 'Bearer ' . $api_key],
      ],
    );
  }

}
