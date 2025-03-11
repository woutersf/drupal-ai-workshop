<?php

namespace Drupal\ai_image;

use Drupal\ai\OperationType\TextToImage\TextToImageInput;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\Core\File\FileUrlGenerator;

/**
 * Process CSV file
 */
class GetAIImage {

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The file repository service.
   *
   * @var \Drupal\file\FileRepositoryInterface
   */
  protected $fileRepository;

  /**
   * The file URL generator service.
   *
   * @var \Drupal\Core\UrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * Constructs a GetAIImage object.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger factory.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system service.
   * @param \Drupal\file\FileRepositoryInterface $fileRepository
   *   The file repository service.
   * @param \Drupal\Core\UrlGeneratorInterface $fileUrlGenerator
   *   The file URL generator service.
   */
  public function __construct(StateInterface $state, LoggerChannelFactoryInterface $loggerFactory, FileSystemInterface $fileSystem, FileRepositoryInterface $fileRepository, FileUrlGenerator $fileUrlGenerator) {
    $this->state = $state;
    $this->logger = $loggerFactory->get('ai_image');
    $this->fileSystem = $fileSystem;
    $this->fileRepository = $fileRepository;
    $this->fileUrlGenerator = $fileUrlGenerator;
  }

  /**
   * Generate the image in the AI provider.
   *
   * @param $provider_name
   * @param $prompt
   *
   * @return \Drupal\Core\GeneratedUrl|string
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function generateImageInAiModule($provider_name, $prompt) {
    $service = \Drupal::service('ai.provider');
    if ($provider_name == '000-AI-IMAGE-DEFAULT') {
      $ai_config = \Drupal::service('config.factory')->get('ai.settings');
      $default_providers = $ai_config->get('default_providers') ?? [];
      $ai_provider = $service->createInstance($default_providers['text_to_image']['provider_id']);
      $default_model = $default_providers['text_to_image']['model_id'];
    }
    else {
      $ai_provider = $service->createInstance($provider_name);
      // TODO if no $default_model how to define this? via the ckeditor admin?
    }
    $config = [
      "n" => 1,
      //"response_format" => "b64_json",
      "response_format" => "url",
      //"size" => "1792x1024",
      "size" => "1024x1024",
      "quality" => "standard",
      "style" => "vivid",
    ];
    $tags = ["tag_1", "tag_2"];
    try {
      $ai_provider->setConfiguration($config);
      $input = new TextToImageInput($prompt);
      $response = $ai_provider->textToImage($input, $default_model, $tags);
      $url = $this->saveAndGetImageUrl($response);

      if ($url) {
        $this->state->set('recent_image', $url);
        $this->state->set('recent_prompt', $prompt);
        return $url;
      }
      else {
        return FALSE;
      }
    } catch (Drupal\ai\Exception\AiUnsafePromptException $e) {
      // TODO should maybe be notified in ckeditor?
      return FALSE;
    }
  }

  /***
   * Generate a URL for this generated image.
   *
   * @param $response
   *
   * @return \Drupal\Core\GeneratedUrl|string
   */
  private function saveAndGetImageUrl($response) {
    $rand = time() . '-' . rand(0, 10000);
    $file_name = $rand . ".png";
    $directory = 'public://ai_image_gen_images/';
    $file_path = $directory . $file_name;

    $file_system = $this->fileSystem;
    $file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    $image_abstractions = $response->getNormalized();
    $images = [];
    foreach ($image_abstractions as $image_abstraction) {
      $images[] = $image_abstraction->getAsFileEntity($file_path);
    }
    if (isset($images[0])) {
      $image_path = $images[0]->getFileUri();
      return $this->fileUrlGenerator
        ->generate($image_path)
        ->toString();
    }
    return FALSE;
  }
}
