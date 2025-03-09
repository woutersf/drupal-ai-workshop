<?php

namespace Drupal\ai_image\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\key\KeyRepositoryInterface;
use Drupal\ai_image\GetAIImage;
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
   * Constructs an AIImgController object.
   *
   * @param \Drupal\key\KeyRepositoryInterface $keyRepository
   *   The key repository service.
   * @param \Drupal\ai_image\GetAIImage $aiImageGenerator
   *   The AI image generator service.
   */
  public function __construct(KeyRepositoryInterface $keyRepository, GetAIImage $aiImageGenerator) {
    $this->keyRepository = $keyRepository;
    $this->aiImageGenerator = $aiImageGenerator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('key.repository'),
      $container->get('ai_image.get_image')
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
    $api = $data->options->source;
    $key_id = $data->options->{$api . '_key'};

    if ($key_id) {
      $key = $this->keyRepository->getKey($key_id)->getKeyValue();

      $imgurl = $this->aiImageGenerator
        ->getImage($prompt, $api, $key);
    }
    if (!$imgurl) {
      $imgurl = '/modules/custom/ai_image/icons/error.jpg';
    }
    return new JsonResponse(
      [
        'text' => trim($imgurl),
      ],
    );
  }


}
