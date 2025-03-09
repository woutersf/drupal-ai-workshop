<?php

namespace Drupal\ai_search_block_log;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The helper class.
 */
class AiSearchBlockLogHelper implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The configuration parameters passed in.
   *
   * @var array
   */
  private $configuration;

  /**
   * The log id.
   *
   * @var int
   */
  private $logId;

  /**
   * The block id.
   *
   * @var string
   */
  private $blockId;

  public function __construct(protected EntityTypeManagerInterface $entityTypeManager, ConfigFactoryInterface $configFactory, Connection $connection) {
    $this->configFactory = $configFactory;
    $this->database = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('database')
    );
  }

  /**
   * Delete the expired logs.
   *
   * @return void
   *   Returns nothing.
   */
  public function cron() {
    $now = time();
    $query = 'DELETE from {ai_search_block_log} where ai_search_block_log.expiry < :param';
    $this->database->query($query, [':param' => (int) $now]);
  }

  /**
   * Open the log line.
   *
   * @param string $block_id
   *   The block.
   * @param int $uid
   *   THe user id.
   * @param string $query
   *   The question.
   *
   * @return int|mixed|string|null
   *   Returns the log item id.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function start($block_id, $uid, $query) {
    $storage = $this->entityTypeManager->getStorage('ai_search_block_log');
    $expiry = $this->configFactory->get('ai_search_block_log.settings')
      ->get('expiry');
    $expiry = $expiry ?? 'week';

    /** @var \Drupal\ai_search_block_log\Entity\AISearchBlockLog $log */
    $log = $storage->create([
      'uid' => $uid,
      'block_id' => $block_id,
      'created' => time(),
      'expiry' => strtotime('now + 1 ' . $expiry),
      'question' => [
        'value' => $query,
        'format' => 'plain_text',
      ],
    ]);
    $log->save();
    return $log->id();
  }

  /**
   * Log the response to the DB.
   *
   * @param \Drupal\ai_search_block_log\int $id
   *   The id to log it in.
   * @param \Drupal\ai_search_block_log\string $response
   *   The response to log.
   *
   * @return void|null
   *   Returns nothing.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function logResponse(int $id, string $response) {
    $entity = $this->entityTypeManager
      ->getStorage('ai_search_block_log')
      ->load($id);
    if (!$entity) {
      return NULL;
    }
    $entity->set('response_given', $response);
    $entity->save();
  }

  /**
   * Update the log with fields.
   *
   * @param \Drupal\ai_search_block_log\int $id
   *   The id of the log item to update.
   * @param array $fields
   *   The fields to update.
   *
   * @return void|null
   *   Returns nothing.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function update(int $id, array $fields) {
    $entity = $this->entityTypeManager
      ->getStorage('ai_search_block_log')
      ->load($id);
    if (!$entity) {
      return NULL;
    }
    foreach ($fields as $key => $field) {
      $entity->set($key, $field);
    }
    $entity->save();
  }

}
