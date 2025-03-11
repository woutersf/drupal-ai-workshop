<?php

namespace Drupal\ai_image\Controller;

use Drupal\ai\AiProviderPluginManager;
use Drupal\Core\Controller\ControllerBase;
use Drupal\key\KeyRepositoryInterface;
use Drupal\ai_image\GetAIImage;
use PhpParser\Error;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for AIImg routes.
 */
class AIImgController extends ControllerBase {

  /**
   * The key repository service.
   *
   * @var \Drupal\key\KeyRepositoryInterface
   */
  protected $keyRepository;

  /**
   * The AI image generator service.
   *
   * @var \Drupal\ai_image\GetAIImage
   */
  protected $aiImageGenerator;

  /**
   * The AI provider manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $aiProviderManager;

  /**
   * Constructs an AIImgController object.
   *
   * @param \Drupal\key\KeyRepositoryInterface $keyRepository
   *   The key repository service.
   * @param \Drupal\ai_image\GetAIImage $aiImageGenerator
   *   The AI image generator service.
   */
  public function __construct(KeyRepositoryInterface $keyRepository, GetAIImage $aiImageGenerator, AiProviderPluginManager $aiProviderManager) {
    $this->keyRepository = $keyRepository;
    $this->aiImageGenerator = $aiImageGenerator;
    $this->aiProviderManager = $aiProviderManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('key.repository'),
      $container->get('ai_image.get_image'),
      $container->get('ai.provider'),
    );
  }

  /**
   * Builds the response.
   */
  public function build() {

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
    ];

    return $build;
  }

  /**
   * Builds the response.
   */
  public function getimage(Request $request): JsonResponse {
    $imgurl = NULL;
    $data = json_decode($request->getContent());
    $prompt = implode(', ', [$data->prompt, $data->options->prompt_extra]);
    $provider_model = $data->options->source;
    $ai_model = '';
    $ai_provider = '';
    try {
      if ($provider_model == '' || $provider_model == '000-AI-IMAGE-DEFAULT') {
        if (empty($parts[0])) {
          $default_model = $this->aiProviderManager->getSimpleDefaultProviderOptions('text_to_image');
          if ($default_model == "") {
            throw new Error('no text-to_image_model selected and no default , can not render.');
          }
          else {
            $parts1 = explode('__', $default_model);
            $ai_provider = $parts1[0];
            $ai_model = $parts1[1];
          }
        }
      }
      else {
        $parts = explode('__', $provider_model);
        $ai_provider = $parts[0];
        $ai_model = $parts[1];
      }
      $imgurl = $this->aiImageGenerator->getImage($ai_provider, $ai_model, $prompt);
    } catch (Exception $exception) {
      $path = \Drupal::service('extension.list.module')->getPath('ai_image');
      $imgurl = '/' . $path . '//icons/error.jpg';
    }
    return new JsonResponse(
      [
        'text' => trim($imgurl),
      ]
    );
  }

}
