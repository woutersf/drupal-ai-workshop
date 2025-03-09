<?php

namespace Drupal\ai_search_block\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\ai_search_block\AiSearchBlockHelper;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * An example controller.
 */
class AiSearchBlockController extends ControllerBase {

  /**
   * The AiSearchBlockHelper.
   *
   * @var \Drupal\ai_search_block\AiSearchBlockHelper
   */
  protected $searchBlockHelper;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The block entity.
   *
   * @var Drupal\block\Entity\Block
   */
  protected $blockEntity;

  /**
   * Constructor.
   *
   * @param \Drupal\ai_search_block\AiSearchBlockHelper $searchBlockHelper
   *   The form builder.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   */
  public function __construct(AiSearchBlockHelper $searchBlockHelper, EntityTypeManagerInterface $entity_manager, AccountProxyInterface $current_user) {
    $this->searchBlockHelper = $searchBlockHelper;
    $this->entityTypeManager = $entity_manager;
    $this->blockEntity = $this->entityTypeManager->getStorage('block');
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Drupal service container.
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai_search_block.helper'),
      $container->get('entity_type.manager'),
      $container->get('current_user')
    );
  }

  /**
   * Returns a renderable array for a test page.
   *
   * Return []
   */
  public function search(Request $request) {
    if ($request->get('block_id')) {
      $query = $request->get('query');
      $block_id = $request->get('block_id');
      $stream = $request->get('stream');
    }
    else {
      $data = Json::decode(file_get_contents('php://input'));
      $query = $data['query'];
      $stream = $data['stream'];
      $block_id = $data['block_id'];
    }
    $block = $this->blockEntity->load($block_id);
    $logId = 0;
    if (function_exists('ai_search_block_log_start')) {
      $logId = ai_search_block_log_start($block_id, $this->currentUser->id(),
        $query);
    }
    if ($block) {
      $settings = $block->get('settings');
      $this->searchBlockHelper->setConfig($settings);
      $this->searchBlockHelper->setBlockId($block_id);
      $this->searchBlockHelper->logId = $logId;
      $results = $this->searchBlockHelper->searchRagAction($query);
      if ($stream == "true" || $stream == "TRUE") {
        header('X-Accel-Buffering: no');
        // Making maximum execution time unlimited.
        set_time_limit(0);
        ob_implicit_flush(1);
        return $results;
      }
      else {
        return $results;
      }
    }
    else {
      if (function_exists('ai_search_block_log_add_response')) {
        ai_search_block_log_add_response($logId, 'There was an error fetching your data');
      }
      return new JsonResponse(
        [
          'response' => 'There was an error fetching your data',
          'log_id' => $logId,
        ]);
    }
  }

}
