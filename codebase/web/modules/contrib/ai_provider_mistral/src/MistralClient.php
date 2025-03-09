<?php

namespace Drupal\ai_provider_mistral;

use Drupal\Component\Serialization\Json;
use GuzzleHttp\Client;

/**
 * Basic custom Mistral Client.
 */
class MistralClient {

  /**
   * The http client.
   */
  protected Client $client;

  /**
   * API Token.
   */
  private string $apiToken;

  /**
   * The Base URL.
   *
   * @var string
   */
  private string $baseUrl = 'https://api.mistral.ai/v1/';

  /**
   * Constructs a new Mistral object.
   *
   * @param \GuzzleHttp\Client $client
   *   Http client.
   */
  public function __construct(Client $client) {
    $this->client = $client;
  }

  /**
   * Set the API token.
   *
   * @param string $apiToken
   *   The API token.
   */
  public function setApiToken($apiToken): void {
    $this->apiToken = $apiToken;
  }

  /**
   * Checks if the api is set.
   *
   * @return bool
   *   If the api is set.
   */
  public function isApiSet(): bool {
    return !empty($this->apiToken);
  }

  /**
   * Moderation endpoint.
   *
   * @param array $json
   *   The json payload.
   *
   * @return array
   *   The moderation output.
   */
  public function moderation(array $json): array {
    $response = $this->makeRequest('moderations', $json);
    return Json::decode($response);
  }

  /**
   * Make Mistral call.
   *
   * @param string $api_endpoint
   *   The api endpoint.
   * @param string $json
   *   JSON params.
   * @param string $file
   *   A (real) filepath.
   * @param string $method
   *   The http method.
   *
   * @return string|object
   *   The return response.
   */
  protected function makeRequest($api_endpoint, $json = NULL, $file = NULL, $method = 'POST'): string|object {
    if (empty($this->apiToken)) {
      throw new \Exception('No Mistral API token found.');
    }

    // We can wait some.
    $options['connect_timeout'] = 120;
    $options['read_timeout'] = 120;
    // Set authorization header.
    $options['headers']['Authorization'] = 'Bearer ' . $this->apiToken;

    if ($json) {
      $options['body'] = Json::encode($json);
      $options['headers']['Content-Type'] = 'application/json';
    }

    if ($file) {
      $options['body'] = fopen($file, 'r');
    }

    $res = $this->client->request($method, $this->baseUrl . $api_endpoint, $options);
    return $res->getBody();
  }

}
