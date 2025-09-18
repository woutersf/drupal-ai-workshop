<?php

namespace Drupal\ai_provider_litellm\DTO;

/**
 * A LiteLLM Model with information about which features are supported.
 */
final readonly class Model {

  /**
   * Whether the model supports image and audio to video.
   *
   * @var bool
   */
  public bool $supportsImageAndAudioToVideo;

  /**
   * Construct a Model object.
   *
   * @param string $name
   *   The name of the model.
   * @param bool $supportsImageInput
   *   Whether the model supports image input.
   * @param bool $supportsImageOutput
   *   Whether the model supports image output.
   * @param bool $supportsAudioInput
   *   Whether the model supports audio input.
   * @param bool $supportsAudioOutput
   *   Whether the model supports audio output.
   * @param bool $supportsVideoOutput
   *   Whether the model supports video output.
   * @param bool $supportsEmbeddings
   *   Whether the model supports embeddings.
   * @param bool $supportsChat
   *   Whether the model supports chat.
   * @param bool $supportsModeration
   *   Whether the model supports moderation.
   * @param string[] $supportedOpenAiParams
   *   The OpenAI compatible params supported by this model.
   */
  public function __construct(
    public string $name,
    public bool $supportsImageInput,
    public bool $supportsImageOutput,
    public bool $supportsAudioInput,
    public bool $supportsAudioOutput,
    public bool $supportsVideoOutput,
    public bool $supportsEmbeddings,
    public bool $supportsChat,
    public bool $supportsModeration,
    public array $supportedOpenAiParams,
  ) {
    $this->supportsImageAndAudioToVideo = $supportsImageInput && $this->supportsAudioInput && $this->supportsVideoOutput;
  }

  /**
   * Create a Model from an API response object.
   *
   * @param \stdClass $response
   *   The object returned by the API from model info.
   *
   * @return self
   *   A model constructed from the API response.
   */
  public static function createFromResponse(\stdClass $response): self {
    $model_info = $response->model_info;
    return new self(
      $response->model_name,
      $model_info->supports_image_input ?? FALSE,
      $model_info->supports_image_output ?? FALSE,
      $model_info->supports_audio_input ?? FALSE,
      $model_info->supports_audio_output ?? FALSE,
      $model_info->supports_video_output ?? FALSE,
      ($model_info->mode ?? NULL) === 'embedding',
      ($model_info->mode ?? NULL) === 'chat',
      $model_info->supports_moderation ?? FALSE,
      $model_info->supported_openai_params ?? [],
    );
  }

}
