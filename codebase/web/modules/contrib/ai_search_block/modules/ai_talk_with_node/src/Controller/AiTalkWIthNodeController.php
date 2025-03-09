<?php

namespace Drupal\ai_talk_with_node\Controller;

use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Controller\TitleResolverInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\ai_search_block\AiSearchBlockHelper;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\file\Entity\File;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\StreamedResponse;


/**
 * An example controller.
 */
class AiTalkWIthNodeController extends ControllerBase {

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
   * Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  protected $aiProviderManager;

  private $logId;

  private $blockId;

  private $configuration;

  protected $titleResolver;
  protected $languageManager;
  protected $configFactory;
  protected $requestStack;



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
  public function __construct(EntityTypeManagerInterface $entity_manager, AccountProxyInterface $current_user, RequestStack $request_stack, TitleResolverInterface $title_resolver, LanguageManagerInterface $languageManager, ConfigFactoryInterface $configFactory, PluginManagerInterface $aiProviderManager, ModuleHandlerInterface $moduleHandler) {
    $this->entityTypeManager = $entity_manager;
    $this->blockEntity = $this->entityTypeManager->getStorage('block');
    $this->currentUser = $current_user;
    $this->requestStack = $request_stack;
    $this->titleResolver = $title_resolver;
    $this->languageManager = $languageManager;
    $this->configFactory = $configFactory;
    $this->aiProviderManager = $aiProviderManager;
    $this->moduleHandler = $moduleHandler;
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
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('request_stack'),
      $container->get('title_resolver'),
      $container->get('language_manager'),
      $container->get('config.factory'),
      $container->get('ai.provider'),
      $container->get('module_handler'),
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
      $block_id = (string) $request->get('block_id');
      $node_id = (int) $request->get('node_id');
      $stream = (bool) $request->get('stream');
    }
    else {
      $data = Json::decode(file_get_contents('php://input'));
      $query = $data['query'];
      $stream = (bool) $data['stream'];
      $block_id = $data['block_id'];
      $node_id = (int) $data['node_id'];
    }
    $block = $this->blockEntity->load($block_id);
    $this->logId = 0;
    if ($this->moduleHandler->moduleExists('ai_search_block_log')) {
      $this->logId = ai_search_block_log_start($block_id, $this->currentUser->id(),
        $query);
    }
    if ($block) {
      $settings = $block->get('settings');
      $this->setConfig($settings);
      $this->setBlockId($block_id);
      $context = $this->loadContextFromFile();
      $context .= $this->loadContextFromField($node_id);
      $results = $this->doChatAction($query, $context, $node_id);
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
        ai_search_block_log_add_response($this->logId, 'There was an error fetching your data');
      }
      return new JsonResponse(
        [
          'response' => 'There was an error fetching your data',
          'log_id' => $this->logId,
        ]);
    }
  }

  private function loadContextFromField($nid){
    $instructions_field_name = $this->configuration['instructions'];
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
    return strip_tags($node->get($instructions_field_name)->getString());
  }
  private function loadContextFromFile(){
    $instructions_fid = $this->configuration['instructions_file'];
    $file = File::load($instructions_fid);
    $uri = $file->getFileUri();
    $stream_wrapper_manager = \Drupal::service('stream_wrapper_manager')->getViaUri($uri);
    $file_path = $stream_wrapper_manager->realpath();
    $instructions_contents = file_get_contents($file_path);
    return $instructions_contents;
  }

  private function doChatAction($query, $context, $node_id){
    $message = str_replace([
      '[question]',
      '[entity]',
    ], [
      $query,
      $context,
    ], $this->configuration['aggregated_llm']);

    foreach ($this->getPrePromptDrupalContext() as $key => $replace) {
      $message = str_replace('[' . $key . ']', is_null($replace) ? '' : (string) $replace, $message);
    }

    $tomorrow = strtotime('+ 1 day');
    $yesterday = strtotime('- 1 day');
    $date_today = date("l M j G:i:s T Y");
    $date_tomorrow = date("l M j G:i:s T Y", $tomorrow);
    $date_yesterday = date("l M j G:i:s T Y", $yesterday);
    $time_now = date("H:i:s");

    $message = str_replace('[time_now]', $time_now, $message);
    $message = str_replace('[date_today]', $date_today, $message);
    $message = str_replace('[date_tomorrow]', $date_tomorrow, $message);
    $message = str_replace('[date_yesterday]', $date_yesterday, $message);

    // Now we have the entity, we can check it with the LLM.
    $ai_provider_model = $this->configuration['llm_model'];

    if ($ai_provider_model === '') {
      $default_provider = $this->aiProviderManager->getDefaultProviderForOperationType('chat');
      $ai_provider_model = $default_provider['provider_id'] . '__' . $default_provider['model_id'];
      $ai_model_to_use = $default_provider['model_id'];
    }
    else {
      $parts = explode('__', $ai_provider_model);
      $ai_model_to_use = $parts[1];
    }
    $provider = $this->aiProviderManager->loadProviderFromSimpleOption($ai_provider_model);
    $temp = $this->configuration['llm_temp'] ?? 0.5;
    $provider->setConfiguration(['temperature' => (float) $temp]);
    $config = [];
    foreach ($this->configuration as $key => $val) {
      $config[$key] = $val;
    }

    $input = new ChatInput([
      new ChatMessage('user', $message),
    ]);


    if ($this->configuration['stream']) {
      $provider->streamedOutput();
      $output = $provider->chat($input, $ai_model_to_use, ['ai_search_block']);
      $response = $output->getNormalized();
      if (is_object($response) && $response instanceof StreamedChatMessageIteratorInterface) {
        return $this->streamBackResponse($response, '$message', $message, [$node_id]);
      }
      else {
        // Ai models that don't stream back?
        $output = $provider->chat($input, $ai_model_to_use, ['ai_search_block']);
        $response = $output->getNormalized()->getText() . "\n";
        //$this->logResponse($response, $message, array_keys([$this->configuration['node_id']]));
        return $response;
      }
    }
    else {
      $output = $provider->chat($input, $ai_model_to_use, ['ai_search_block']);
      $response = $output->getNormalized()->getText() . "\n";
      //$this->logResponse($response, $message, array_keys([$this->configuration['node_id']]));
      return new JsonResponse(
        [
          'response' => $response,
          'log_id' => $this->logId,
        ]);
    }
  }

  /**
   * Get preprompt Drupal context.
   *
   * @return string[]
   *   This is the Drupal context that you can add to the pre prompt.
   */
  public function getPrePromptDrupalContext() {
    $context = [];
    $current_request = $this->requestStack->getCurrentRequest();
    $context['is_logged_in'] = $this->currentUser->isAuthenticated() ? 'is logged in' : 'is not logged in';
    $context['user_roles'] = implode(', ', $this->currentUser->getRoles());
    $context['user_id'] = $this->currentUser->id();
    $context['user_name'] = $this->currentUser->getDisplayName();
    $context['user_language'] = $this->currentUser->getPreferredLangcode();
    $context['user_timezone'] = $this->currentUser->getTimeZone();
    $context['page_title'] = (string) $this->titleResolver->getTitle($current_request, $current_request->attributes->get('_route_object'));
    $context['page_path'] = $current_request->getRequestUri();
    $context['page_language'] = $this->languageManager->getCurrentLanguage()->getId();
    $context['site_name'] = $this->configFactory->get('system.site')->get('name');

    return $context;
  }

  /**
   * Set the config for this Search.
   *
   * @param array $config
   *   The array wth configuration.
   *
   * @return void
   *   No return needed.
   */
  public function setConfig($config) {
    $this->configuration = $config;
  }

  /**
   * The block ID (for logging).
   *
   * @param string $block_id
   *   The id of the block.
   *
   * @return void
   *   Nothing returned.
   */
  public function setBlockId($block_id) {
    $this->blockId = $block_id;
  }

  private function streamBackResponse($parts, $type, $prompt, $result_items) {
    return new StreamedResponse(function () use ($type, $parts, $prompt, $result_items) {
      $log_output = '';
      foreach ($parts as $part) {
        $item = [];
        $item['in_html'] = FALSE;
        $item['log_id'] = $this->logId;
        if ($type == 'string') {
          $item['answer_piece'] = $part;
        }
        else {
          $item['answer_piece'] = $part->getText();
        }
        $out = Json::encode($item);
        $log_output .= $item['answer_piece'];
        unset($item);
        echo $out . '|§|';
        ob_flush();
        flush();
        if ($type == 'string') {
          usleep(50000);
        }
      }
      $this->logResponse($log_output, $prompt, $result_items);
    }, 200, [
      'Cache-Control' => 'no-cache, must-revalidate',
      'Content-Type' => 'text/event-stream',
      'X-Accel-Buffering' => 'no',
    ]);
  }


  private function logResponse($response, $prompt, $items) {
    if ($this->moduleHandler->moduleExists('ai_search_block_log')) {
      ai_search_block_log_update($this->logId, [
        'prompt_used' => $prompt,
        'response_given' => $response,
        'detailed_output' => Json::encode($items),
      ]);
    }
  }

}
