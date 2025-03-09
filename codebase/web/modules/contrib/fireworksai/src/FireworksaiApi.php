<?php

namespace Drupal\fireworksai;

use Drupal\Core\Config\ConfigFactory;
use Drupal\file\FileInterface;
use Drupal\fireworksai\Form\FireworksaiConfigForm;
use GuzzleHttp\Client;

/**
 * Fireworksai API creator.
 */
class FireworksaiApi {

  /**
   * The http client.
   */
  protected Client $client;

  /**
   * API Key.
   */
  private string $apiKey;

  /**
   * The base host.
   */
  private string $baseHost = 'https://api.fireworks.ai/inference/v1/';

  /**
   * Constructs a new Fireworks AI object.
   *
   * @param \GuzzleHttp\Client $client
   *   Http client.
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   *   The config factory.
   */
  public function __construct(Client $client, ConfigFactory $configFactory) {
    $this->client = $client;
  }

  /**
   * Set API key.
   *
   * @param string $apiKey
   *   The api key.
   */
  public function setApiKey($apiKey) {
    $this->apiKey = $apiKey;
  }

  /**
   * Get all models in Fireworksai.
   *
   * @return array
   *   The response.
   */
  public function getModels() {
    $result = json_decode($this->makeRequest("models", [], 'GET'), TRUE);
    return $result;
  }

  /**
   * Get firework models.
   *
   * @return array
   *   The response.
   */
  public function getFireworkModels() {
    $filtered = [];
    $result = $this->getModels();
    foreach ($result['data'] as $model) {
      if (substr($model['id'], 0, 18) == 'accounts/fireworks') {
        $filtered[] = $model;
      }
    }
    return $filtered;
  }

  /**
   * Completion call.
   *
   * @param array $prompts
   *   The prompts.
   * @param string $model
   *   The model.
   * @param array $images
   *   The base64 images if its an image model.
   * @param array $options
   *   Extra options to send.
   *
   * @return string|object
   *   The response.
   */
  public function completion(array $prompts, $model, array $images = [], array $options = []) {
    $body = $options;
    $body = [
      'prompt' => $prompts,
      'model' => $model,
    ];
    if ($images) {
      $body['images'] = $images;
    }
    return $this->makeRequest('completions', [], 'POST', $body);
  }

  /**
   * Chat completion.
   *
   * @param array $messages
   *   The messages.
   * @param string $model
   *   The model.
   * @param array $options
   *   Extra options to send.
   */
  public function chatCompletion(array $messages, $model, array $options = []) {
    $body = $options;
    $body = [
      'messages' => $messages,
      'model' => $model,
    ];
    return $this->makeRequest('chat/completions', [], 'POST', $body);
  }

  /**
   * Text-To-Image generation call.
   *
   * @param string $prompt
   *   The prompt.
   * @param string $model
   *   The model.
   * @param string $imageType
   *   The image type.
   * @param int $width
   *   The width.
   * @param int $height
   *   The height.
   * @param array $options
   *   Extra options to send.
   */
  public function textToImage($prompt, $model, $imageType, $width, $height, array $options = []) {
    $body = $options;
    $body = [
      'prompt' => $prompt,
      'model' => $model,
      'width' => $width,
      'height' => $height,
    ];
    $guzzleOptions = [];
    if ($imageType) {
      $guzzleOptions['headers']['accept'] = $imageType;
    }
    return $this->makeRequest('image_generation/accounts/fireworks/models/' . $model, [], 'POST', $body, $guzzleOptions);
  }

  /**
   * Image to Image.
   *
   * @param string $prompt
   *   The prompt.
   * @param string $model
   *   The model.
   * @param string $imageType
   *   The image type.
   * @param Drupal\file\FileInterface $inputImage
   *   The input image.
   * @param array $options
   *   Extra options to send.
   */
  public function imageToImage($prompt, $model, $imageType, FileInterface $inputImage, array $options = []) {
    $guzzleOptions['multipart'] = [
      [
        'name' => 'init_image',
        'contents' => fopen($inputImage->getFileUri(), 'r'),
        'filename' => $inputImage->getFilename(),
      ],
      [
        'name' => 'prompt',
        'contents' => $prompt,
      ],
      [
        'name' => 'model',
        'contents' => $model,
      ],
    ];

    // Add extra options.
    foreach ($options as $key => $value) {
      if (is_array($value)) {
        foreach ($value as $subValue) {
          $guzzleOptions['multipart'][] = [
            'name' => $key,
            'contents' => $subValue,
          ];
        }
      }
      else {
        $guzzleOptions['multipart'][] = [
          'name' => $key,
          'contents' => $value,
        ];
      }
    }

    if ($imageType) {
      $guzzleOptions['headers']['accept'] = $imageType;
    }
    return $this->makeRequest('image_generation/accounts/fireworks/models/' . $model . '/image_to_image', [], 'POST', NULL, $guzzleOptions);
  }

  /**
   * Qr generation for controlnet.
   *
   * @param string $model
   *   The model.
   * @param string $string
   *   The string to create for.
   *
   * @return string|object
   *   The response.
   */
  public function qrCode($model, $string) {
    $guzzleOptions = [];
    $data['prompt'] = $string;
    return $this->makeRequest('image_generation/accounts/fireworks/models/' . $model . '/qr_code', [], 'POST', $data, $guzzleOptions);
  }

  /**
   * Canny edge detenction.
   *
   * @param string $model
   *   The model.
   * @param Drupal\file\FileInterface $inputImage
   *   The input image.
   * @param string $imageType
   *   The image type.
   *
   * @return string|object
   *   The response.
   */
  public function cannyEdgeDetection($model, FileInterface $inputImage, $imageType = NULL) {
    $guzzleOptions['multipart'] = [
      [
        'name' => 'image',
        'contents' => fopen($inputImage->getFileUri(), 'r'),
        'filename' => $inputImage->getFilename(),
      ],
    ];
    if ($imageType) {
      $guzzleOptions['headers']['accept'] = $imageType;
    }
    return $this->makeRequest('image_generation/accounts/fireworks/models/' . $model . '/canny_edge_detection', [], 'POST', NULL, $guzzleOptions);
  }

  /**
   * Image to ControlNet.
   *
   * @param string $prompt
   *   The prompt.
   * @param string $model
   *   The model.
   * @param string $imageType
   *   The image type.
   * @param Drupal\file\FileInterface $inputImage
   *   The input image.
   * @param array $options
   *   Extra options to send.
   */
  public function controlnet($prompt, $model, $imageType, FileInterface $inputImage, array $options = []) {
    $guzzleOptions['multipart'] = [
      [
        'name' => 'control_image',
        'contents' => fopen($inputImage->getFileUri(), 'r'),
        'filename' => $inputImage->getFilename(),
      ],
      [
        'name' => 'prompt',
        'contents' => $prompt,
      ],
      [
        'name' => 'seed',
        'contents' => 0,
      ],
    ];

    // Add extra options.
    foreach ($options as $key => $value) {
      if (is_array($value)) {
        foreach ($value as $subValue) {
          $guzzleOptions['multipart'][] = [
            'name' => $key,
            'contents' => $subValue,
          ];
        }
      }
      else {
        $guzzleOptions['multipart'][] = [
          'name' => $key,
          'contents' => $value,
        ];
      }
    }

    if ($imageType) {
      $guzzleOptions['headers']['accept'] = $imageType;
    }
    return $this->makeRequest('image_generation/accounts/fireworks/models/' . $model . '/control_net', [], 'POST', NULL, $guzzleOptions)->getContents();
  }

  /**
   * Make embeddings create endpoint.
   *
   * @param string $model
   *   The model.
   * @param string $text
   *   The text.
   * @param array $options
   *   Extra options to send.
   *
   * @return string|object
   *   The response.
   */
  public function embeddingsCreate($model, $text, $options = []) {
    $body = $options;
    $body = [
      'model' => $model,
      'input' => $text,
    ];
    return $this->makeRequest('embeddings', [], 'POST', $body);
  }

  /**
   * Make fireworksai call.
   *
   * @param string $path
   *   The path.
   * @param array $query_string
   *   The query string.
   * @param string $method
   *   The method.
   * @param string $body
   *   Data to attach if POST/PUT/PATCH.
   * @param array $options
   *   Extra headers.
   *
   * @return string|object
   *   The return response.
   */
  protected function makeRequest($path, array $query_string = [], $method = 'GET', $body = '', array $options = []) {
    if (!$this->apiKey) {
      throw new \Exception('No api key set.');
    }
    // Don't wait to long.
    $options['connect_timeout'] = 120;
    $options['read_timeout'] = 120;
    $options['timeout'] = 120;

    // JSON unless its multipart.
    if (empty($options['multipart'])) {
      $options['headers']['Content-Type'] = 'application/json';
    }

    // Credentials.
    $options['headers']['authorization'] = 'Bearer ' . $this->apiKey;
    if ($body) {
      $options['body'] = json_encode($body);
    }

    $new_url = rtrim($this->baseHost, '/') . '/' . $path;
    $new_url .= count($query_string) ? '?' . http_build_query($query_string) : '';

    $res = $this->client->request($method, $new_url, $options);

    return $res->getBody();
  }

}
