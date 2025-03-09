<?php

namespace Drupal\ai_vdb_provider_pinecone;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Probots\Pinecone\Client as PineconeClient;

/**
 * Extends Pinecone with extra calls.
 */
class Pinecone {
  use StringTranslationTrait;

  /**
   * Pinecone client.
   *
   * @var \Probots\Pinecone\Client
   */
  private PineconeClient $client;

  /**
   * Construct the Pinecone wrapper for the API.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The default cache bin.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The logger factory.
   */
  public function __construct(
    protected CacheBackendInterface $cache,
    protected MessengerInterface $messenger,
    protected LoggerChannelFactoryInterface $loggerChannelFactory,
  ) {
  }

  /**
   * The Pinecone described indexes.
   *
   * @var array
   */
  private array $indexes = [];

  /**
   * Get the client.
   *
   * @param string $api_key
   *   The Pinecone client.
   *
   * @return \Drupal\ai_vdb_provider_pinecone\Pinecone
   *   The Pinecone client.
   */
  public function getClient(string $api_key): Pinecone {
    if (isset($this->client) && $this->client) {
      return $this;
    }
    $this->client = new PineconeClient($api_key);
    return $this;
  }

  /**
   * Helper method to get the client preconfigured for a specific index.
   *
   * @param string $index_name
   *   The index name.
   *
   * @return \Probots\Pinecone\Client
   *   The Pinecone client.
   */
  protected function getClientForIndex(string $index_name): PineconeClient {
    $index = $this->describeIndex($index_name);
    $this->client->setIndexHost('https://' . $index['host']);
    return $this->client;
  }

  /**
   * Clear the indexes cache.
   */
  public function clearIndexesCache(): void {
    $this->indexes = [];
    $cid = 'pinecone:' . Crypt::hashBase64($this->client->apiKey);
    $this->cache->delete($cid);
  }

  /**
   * Get all indexes available via the Pinecone API.
   *
   * @return array
   *   The available indexes.
   */
  public function listIndexes(): array {
    if ($this->indexes) {
      return $this->indexes;
    }
    $cid = 'pinecone:' . Crypt::hashBase64($this->client->apiKey);
    if ($cache = $this->cache->get($cid)) {
      return $cache->data;
    }
    try {
      $response = $this->client->control()->index()->list();
      if ($response->successful() && !empty($response->array()['indexes'])) {
        $indexes = $response->array()['indexes'];
        $this->cache->set($cid, $indexes);
        return $indexes;
      }
    }
    catch (\Exception $exception) {
      $this->messenger->addWarning($this->t('An exception occurred: @exception', [
        '@exception' => $exception->getMessage(),
      ]));
    }
    return [];
  }

  /**
   * Describe an index.
   *
   * @param string $index_name
   *   The index name.
   *
   * @return array
   *   The index described.
   */
  public function describeIndex(string $index_name): array {
    foreach ($this->listIndexes() as $index) {
      if ($index['name'] === $index_name) {
        return $index;
      }
    }
    return [];
  }

  /**
   * Gets all index stats.
   */
  public function getIndexStats(string $index_name): array {
    $response = $this->getClientForIndex($index_name)->data()->vectors()->stats();
    if ($response->successful()) {
      return $response->array();
    }
    return [];
  }

  /**
   * Insert into the collection.
   *
   * @param string $namespace
   *   The namespace.
   * @param array $data
   *   The data.
   * @param string $index_name
   *   The index name.
   */
  public function insertIntoNamespace(string $namespace, array $data, string $index_name): void {
    $metadata = $data;
    unset($metadata['vector']);

    // Pinecone metadata only supports nested strings. This is their "List of
    // strings" metadata option.
    foreach ($metadata as &$item) {
      if (is_array($item)) {
        foreach ($item as &$nested_item) {
          $nested_item = (string) $nested_item;
        }
      }
    }

    try {
      $this->getClientForIndex($index_name)->data()->vectors()->upsert(
        vectors: [
          'id' => $data['drupal_long_id'],
          'values' => $data['vector'],
          'metadata' => $metadata,
        ],
        namespace: $namespace,
      );
    }
    catch (\Exception $exception) {
      $this->messenger->addWarning($this->t('An exception occurred while attempting to insert or update data in Pinecone: @exception', [
        '@exception' => $exception->getMessage(),
      ]));
      $logger = $this->loggerChannelFactory->get('ai_search');
      $logger->warning('An exception occurred while attempting to insert or update data in Pinecone: @exception', [
        '@exception' => $exception->getMessage(),
      ]);
    }
  }

  /**
   * Delete from the namespace.
   *
   * @param string $namespace
   *   The namespace.
   * @param array $ids
   *   The ids.
   * @param string $index_name
   *   The index name.
   */
  public function deleteFromNamespace(string $namespace, array $ids, string $index_name): void {
    $index_stats = $this->getIndexStats($index_name);
    if (
      isset($index_stats['namespaces'][$namespace]['vectorCount'])
      && $index_stats['namespaces'][$namespace]['vectorCount'] > 0
    ) {
      $this->getClientForIndex($index_name)->data()->vectors()->delete(
        ids: $ids,
        namespace: $namespace,
      );
    }
  }

  /**
   * Delete all from the namespace.
   *
   * @param string $namespace
   *   The namespace.
   * @param string $index_name
   *   The index name.
   */
  public function deleteAllFromNamespace(string $namespace, string $index_name): void {
    $index_stats = $this->getIndexStats($index_name);
    if (
      isset($index_stats['namespaces'][$namespace]['vectorCount'])
      && $index_stats['namespaces'][$namespace]['vectorCount'] > 0
    ) {
      $this->getClientForIndex($index_name)->data()->vectors()->delete(
        deleteAll: TRUE,
        namespace: $namespace,
      );
    }
  }

  /**
   * Look up and returns vectors by ID, from a single namespace.
   *
   * @param string $namespace
   *   The namespace.
   * @param array $ids
   *   The IDs to fetch within the namespace.
   * @param string $index_name
   *   The index name.
   *
   * @return array
   *   The IDs.
   */
  public function fetch(string $namespace, array $ids, string $index_name): array {
    $response = $this->getClientForIndex($index_name)
      ->data()
      ->vectors()
      ->fetch($ids, $namespace);
    if ($response->successful() && !empty($response->array()['vectors'])) {
      return $response->array()['vectors'];
    }
    return [];
  }

  /**
   * Query the vector database without providing vector values.
   *
   * @param string $namespace
   *   The collection.
   * @param string $index_name
   *   The index name.
   * @param array $filter
   *   The filters as a PHP array to be converted via JSON Encode to match the
   *   expected format in the Pinecone documentation:
   *   https://docs.pinecone.io/guides/data/filter-with-metadata.
   * @param int $topK
   *   The number of results to return.
   * @param array $vector
   *   The vector array to search by. Leave as an empty array for no search.
   *
   * @return array
   *   The response.
   */
  public function query(string $namespace, string $index_name, array $filter = [], int $topK = 10, array $vector = []): array {
    $response = $this->getClientForIndex($index_name)->data()->vectors()->query(
      vector: $vector,
      namespace: $namespace,
      filter: $filter,
      topK: $topK,
    );
    if ($response->successful() && !empty($response->array()['matches'])) {
      return $response->array()['matches'];
    }
    return [];
  }

}
