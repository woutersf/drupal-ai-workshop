<?php

namespace Drupal\ai_image;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\TextToImage\TextToImageInput;
use Drupal\Core\Extension\ModuleHandlerInterface;
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
   * The provider plugin manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $aiProviderManager;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;


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
  public function __construct(StateInterface $state, LoggerChannelFactoryInterface $loggerFactory, FileSystemInterface $fileSystem, FileRepositoryInterface $fileRepository, FileUrlGenerator $fileUrlGenerator, AiProviderPluginManager $ai_provider_manager,ModuleHandlerInterface $module_handler ) {
    $this->state = $state;
    $this->logger = $loggerFactory->get('ai_image');
    $this->fileSystem = $fileSystem;
    $this->fileRepository = $fileRepository;
    $this->fileUrlGenerator = $fileUrlGenerator;
    $this->aiProviderManager = $ai_provider_manager;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   *
   * @param String $prompt
   *   The prompt string.
   * @param String $api
   *   The image generation engine.
   * @param String $api_key
   *   API secret key.
   *
   * @return int
   *   The count of rows processed
   */
  public function getImage(string $provider, string $model, string $prompt) {
    return $this->getAiIMage($provider, $model, $prompt);
  }

  private function getAiIMage($provider, $model, $prompt) {
    $config = [];
    if ($provider == 'openai') {
      $config = [
        "n" => 1,
        "response_format" => "url",
        "size" => '1024x1024',
        "quality" => "standard",
        "style" => "vivid",
      ];
    }
    if (str_contains($model,'stable-diffusion')) {
      $config = [
        //        "prompt" => $prompt,
        "response_format" => "url",
        "negative_prompt"=> "((out of frame)), ((extra fingers)), mutated hands, ((poorly drawn hands)), ((poorly drawn face)), (((mutation))), (((deformed))), (((tiling))), ((naked)), ((tile)), ((fleshpile)), ((ugly)), (((abstract))), blurry, ((bad anatomy)), ((bad proportions)), ((extra limbs)), cloned face, (((skinny))), glitchy, ((extra breasts)), ((double torso)), ((extra arms)), ((extra hands)), ((mangled fingers)), ((missing breasts)), (missing lips), ((ugly face)), ((fat)), ((extra legs)), anime",
        "cfg_scale" => null,
        "image_size" => "1024x1024",
        "size" => "1024x1024",
        //        "width"=> 1024,
        //        "height"=> 1024,
        "samples"=> "1",
        "steps"=> null,
        "sampler"=> 'None',
        "num_inference_steps"=> "20",
        "seed"=> 0,
        "guidance_scale"=> 7.5,
        "webhook"=> null,
        "track_id"=> null,
        "accept" =>  "image/jpeg",
        "output_image_format" => 'JPG',
      ];
    }

    // Allow overriding of the config passed in to the AI image generation.
    $hook = 'ai_image_alter_config';
    $this->moduleHandler->invokeAllWith($hook, function (callable $hook, string $module) use (&$config, $model, $provider) {
      $config = $hook();
    });

    $ai_provider = $this->aiProviderManager->createInstance($provider);
    $ai_provider->setConfiguration($config);
    $input = new TextToImageInput($prompt);
    // This gets an array of \Drupal\ai\OperationType\GenericType\ImageFile.
    $normalized = $ai_provider->textToImage($input, $model, ["ai_image"])->getNormalized();
    $file = $normalized[0]->getAsFileEntity("public://", "generated_image.png");
    return $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
  }
}

