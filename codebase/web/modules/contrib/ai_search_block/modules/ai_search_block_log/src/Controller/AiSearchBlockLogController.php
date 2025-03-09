<?php

namespace Drupal\ai_search_block_log\Controller;

use Drupal\Core\Controller\ControllerBase;
// Adjust namespace if necessary.
use Drupal\ai_search_block_log\AiSearchBlockLogHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * An example controller.
 */
class AiSearchBlockLogController extends ControllerBase {

  /**
   * The log helper service.
   *
   * @var \Drupal\ai_search_block_log\AiSearchBlockLogHelper
   */
  protected AiSearchBlockLogHelper $helper;

  /**
   * Constructs a new AiSearchBlockLogController.
   *
   * @param \Drupal\ai_search_block_log\AiSearchBlockLogHelper $helper
   *   The log helper service.
   */
  public function __construct(AiSearchBlockLogHelper $helper) {
    $this->helper = $helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai_search_block_log.helper')
    );
  }

  /**
   * Returns a renderable array for a test page.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response.
   */
  public function score(Request $request): JsonResponse {
    $logId = NULL;
    $score = 0;
    if ($request->get('log_id')) {
      $logId = $request->get('log_id');
      $score = $request->get('score');
      // Use the injected helper service.
      $this->helper->update((int) $logId, ['score' => (int) $score]);
      if ($feedback = $request->get('feedback')) {
        $this->helper->update((int) $logId, ['feedback' => $feedback]);
      }

      // @todo Make this configurable.
      return new JsonResponse([
        'response' => $this->t('Thank you for your feedback.'),
      ]);
    }
    die('Whoops');
  }

}
