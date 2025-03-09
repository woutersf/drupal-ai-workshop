<?php

namespace Drupal\ai_api\Plugin\AiApiAccessPoint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai_api\Attribute\AiApiAccessPoint;
use Drupal\ai_api\PluginBase\BaseAccessPoint;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The OpenAI standardized chat endpoint.
 */
#[AiApiAccessPoint(
  id: 'open_ai_chat',
  label: new TranslatableMarkup('OpenAI Based Chat'),
  operation_type: 'chat',
  methods: ['POST'],
  endpoint: '/v1/chat/completions',
)]
class OpenAIChat extends BaseAccessPoint {

  /**
   * {@inheritDoc}
   */
  public function runRequest(Request $request): Response {
    // Get the request body.
    $data = json_decode($request->getContent(), TRUE);
    if (!isset($data['messages']) || empty($data['messages'])) {
      return new JsonResponse([
        'error' => 'No messages provided.',
      ], 400);
    }

    // Load the model if wanted.
    $provider = NULL;
    $model = NULL;
    if ($request->get('provider') && $request->get('model')) {
      try {
        $provider = $this->aiProvider->createInstance($request->get('provider'));
      }
      catch (\Exception $e) {
        return new JsonResponse([
          'error' => 'Invalid provider given.',
        ], 400);
      }
      $model = $request->get('model');
    }
    else {
      $defaults = explode('__', $this->aiProvider->getSimpleDefaultProviderOptions('chat'));
      if (!count($defaults) == 2) {
        return new JsonResponse([
          'error' => 'Invalid default provider options.',
        ], 500);
      }
      $provider = $this->aiProvider->createInstance($defaults[0]);
      $model = $defaults[1];
    }

    $messages = [];

    // Run through each request.
    foreach ($data['messages'] as $message) {
      if (!isset($message['role']) || !isset($message['content'])) {
        return new JsonResponse([
          'error' => 'Invalid message format.',
        ], 400);
      }
      if ($message['role'] == 'system') {
        $provider->setSystemMessage($message['content']);
      }
      else {
        $message = new ChatMessage($message['role'], $message['content']);
        $messages[] = $message;
      }
    }
    $input = new ChatInput($messages);
    $ai_response = $provider->chat($input, $model, [
      'ai_api',
      'open_ai_chat',
    ]);

    $output_message = $ai_response->getNormalized();
    $raw_data = $ai_response->getRawOutput();

    // Create an OpenAI Response.
    $response['id'] = 'chatcmpl-' . $this->generateRandomString();
    $response['object'] = 'chat_completion';
    $response['created'] = time();
    $response['model'] = $model;
    $response['system_fingerprint'] = 'fp_' . $this->createHex();
    $response['choices'] = [];
    $response['choices'][] = [
      'index' => 0,
      'message' => [
        'role' => $output_message->getRole(),
        'content' => $output_message->getText(),
      ],
      'finish_reason' => 'stop',
    ];
    // @todo Get actual usage.
    $response['usage'] = [
      'prompt_tokens' => $raw_data['usage']['prompt_tokens'] ?? 0,
      'completion_tokens' => $raw_data['usage']['completion_tokens'] ?? 0,
      'total_tokens' => $raw_data['usage']['total_tokens'] ?? 0,
      'prompt_token_details' => [
        'cached_tokens' => $raw_data['usage']['prompt_token_details']['cached_tokens'] ?? 0,
        'audio_tokens' => $raw_data['usage']['prompt_token_details']['audio_tokens'] ?? 0,
      ],
      'completion_token_details' => [
        'reasoning_tokens' => $raw_data['usage']['completion_token_details']['reasoning_tokens'] ?? 0,
        'accepted_prediction_tokens' => $raw_data['usage']['completion_token_details']['accepted_prediction_tokens'] ?? 0,
        'rejected_prediction_tokens' => $raw_data['usage']['completion_token_details']['rejected_prediction_tokens'] ?? 0,
        'audio_tokens' => $raw_data['usage']['completion_token_details']['audio_tokens'] ?? 0,
      ],
    ];
    return new JsonResponse($response);
  }

  /**
   * Generate 30 char random string.
   *
   * @return string
   *   The random string.
   */
  protected function generateRandomString(): string {
    return bin2hex(random_bytes(15));
  }

  /**
   * Create a 11 char hex string.
   *
   * @return string
   *   The hex string.
   */
  protected function createHex(): string {
    return bin2hex(random_bytes(5));
  }

}
