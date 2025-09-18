<?php

namespace Drupal\ai_vdb_provider_pinecone\Plugin\VdbProvider;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\AiVdbProvider;
use Drupal\ai\Base\AiVdbProviderClientBase;
use Drupal\ai\Enum\VdbSimilarityMetrics;
use Drupal\ai_vdb_provider_pinecone\Pinecone;
use Drupal\key\KeyRepositoryInterface;
use Drupal\search_api\Query\ConditionGroupInterface;
use Drupal\search_api\Query\QueryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Plugin implementation of the 'Pinecone' provider.
 */
#[AiVdbProvider(
  id: 'pinecone',
  label: new TranslatableMarkup('Pinecone DB'),
)]
class PineconeProvider extends AiVdbProviderClientBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The API key.
   *
   * @var string
   */
  protected string $apiKey = '';

  /**
   * Constructs an override for the AiVdbClientBase class to add Milvus V2.
   *
   * @param string $pluginId
   *   Plugin ID.
   * @param mixed $pluginDefinition
   *   Plugin definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\key\KeyRepositoryInterface $keyRepository
   *   The key repository.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\ai_vdb_provider_pinecone\Pinecone $pinecone
   *   The Pinecone API client.
   */
  public function __construct(
    protected string $pluginId,
    protected mixed $pluginDefinition,
    protected ConfigFactoryInterface $configFactory,
    protected KeyRepositoryInterface $keyRepository,
    protected EventDispatcherInterface $eventDispatcher,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected MessengerInterface $messenger,
    protected Pinecone $pinecone,
  ) {
    parent::__construct(
      $this->pluginId,
      $this->pluginDefinition,
      $this->configFactory,
      $this->keyRepository,
      $this->eventDispatcher,
      $this->entityFieldManager,
      $this->messenger,
    );
  }

  /**
   * Load from dependency injection container.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): AiVdbProviderClientBase|static {
    return new static(
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('key.repository'),
      $container->get('event_dispatcher'),
      $container->get('entity_field.manager'),
      $container->get('messenger'),
      $container->get('pinecone.api'),
    );
  }

  /**
   * Get the Pinecone client.
   *
   * @return \Drupal\ai_vdb_provider_pinecone\Pinecone
   *   The Pinecone client.
   */
  public function getClient($host = TRUE): Pinecone {
    $key_name = $this->getConfig()->get('api_key');
    $key_value = $this->keyRepository->getKey($key_name)->getKeyValue();
    return $this->pinecone->getClient($key_value);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(): ImmutableConfig {
    return $this->configFactory->get('ai_vdb_provider_pinecone.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function buildSettingsForm(
    array $form,
    FormStateInterface $form_state,
    array $configuration,
  ): array {
    // Don't load from cache here.
    $this->getClient()->clearIndexesCache();

    $form = parent::buildSettingsForm($form, $form_state, $configuration);

    // Force the user to select from an index created via Pinecone UI.
    // This greatly simplifies what we need to handle in this module.
    unset($form['database_name']['#pattern']);
    $form['database_name']['#type'] = 'select';
    $form['database_name']['#options'] = [];
    $indexes = $this->getClient()->listIndexes();
    foreach ($indexes as $index) {
      $form['database_name']['#options'][$index['name']] = $index['name'];
    }
    if (isset($configuration['database_settings']['database_name'])) {
      $form['database_name']['#default_value'] = $configuration['database_settings']['database_name'];
    }

    // Override the collection description.
    $form['collection']['#title'] = $this->t('Namespace');
    $form['collection']['#description'] = $this->t('The namespace within the Pinecone Index to store the data from this Search API Server. Each Search API Server for Pinecone may have only 1 index since the Search API Server is mapped to the Pinecone Namespace.');
    if (isset($configuration['database_settings']['collection'])) {
      $form['collection']['#default_value'] = $configuration['database_settings']['collection'];
    }

    // Override the metric labels to match Pinecone documentation.
    $metric_distance = [
      VdbSimilarityMetrics::InnerProduct->value => $this->t('DotProduct'),
    ];
    $form['metric']['#options'] = array_merge($form['metric']['#options'], $metric_distance);
    if (isset($configuration['database_settings']['metric'])) {
      $form['metric']['#default_value'] = $configuration['database_settings']['metric'];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateSettingsForm(array &$form, FormStateInterface $form_state): void {
    $database_settings = $form_state->getValue('database_settings');
    if (empty($database_settings['database_name'])) {
      $form_state->setErrorByName('backend_config][database_name', $this->t('Ensure that your Pinecone API key is correct and that you have created at least one Index in the Pinecone UI.'));
      return;
    }

    $collections = $this->getCollections($database_settings['database_name']);

    // Check that the collection doesn't exist already.
    $form_object = $form_state->getFormObject();
    $entity = $form_object->getEntity();
    if ($entity->isNew() && in_array($database_settings['collection'], $collections)) {
      $form_state->setErrorByName('collection', $this->t('The collection already exists in the selected vector database.'));
    }

    // Check that the metric selected matches the selected index.
    $index = $this->getClient()->describeIndex($database_settings['database_name']);
    $metric_map = [
      'cosine' => VdbSimilarityMetrics::CosineSimilarity->value,
      'euclidean' => VdbSimilarityMetrics::EuclideanDistance->value,
      'dotproduct' => VdbSimilarityMetrics::InnerProduct->value,
    ];
    if (!isset($index['metric']) || !isset($metric_map[$index['metric']])) {
      $form_state->setErrorByName('database_settings][metric', $this->t('Unable to determine the metric from the selected index.'));
    }
    elseif ($metric_map[$index['metric']] !== $database_settings['metric']) {
      $form_state->setErrorByName('database_settings][metric', $this->t('The selected metric "@selected_metric" does not match the metric from the chosen index "@chosen_index_metric".', [
        '@chosen_index_metric' => $index['metric'],
        '@selected_metric' => $database_settings['metric'],
      ]));
    }

    // Check that the dimensions match that of the index.
    if ((int) $form_state->getValue('embeddings_engine_configuration')['dimensions'] !== (int) $index['dimension']) {
      $form_state->setErrorByName('backend_config[embeddings_engine_configuration][dimensions', $this->t('The selected dimensions "@selected_dimensions" does not match the dimensions from the chosen index "@chosen_index_dimensions".', [
        '@chosen_index_dimensions' => $index['dimension'],
        '@selected_dimensions' => $form_state->getValue('embeddings_engine_configuration')['dimensions'],
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitSettingsForm(array &$form, FormStateInterface $form_state): void {
    $database_settings = $form_state->getValue('database_settings');
    $this->createCollection(
      collection_name: $database_settings['collection'],
      dimension: $form_state->getValue('embeddings_engine_configuration')['dimensions'],
      metric_type: VdbSimilarityMetrics::from($database_settings['metric']),
      database: $database_settings['database_name'],
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewIndexSettings(array $database_settings): array {
    // Don't load from cache here.
    $this->getClient()->clearIndexesCache();

    $results = [];
    $results['ping'] = [
      'label' => $this->t('Ping'),
      'info' => $this->t('Able to reach Pinecone via their API.'),
      'status' => $this->ping() ? 'success' : 'error',
    ];

    if (!empty($database_settings['database_name'])) {
      $described = $this->getClient()->describeIndex($database_settings['database_name']);
      if (isset($described['host'])) {
        $results['host'] = [
          'label' => $this->t('Host'),
          'info' => $described['host'],
        ];
      }
      if (isset($described['status']['state'])) {
        $results['state'] = [
          'label' => $this->t('State'),
          'info' => $described['status']['state'],
        ];
      }
      if (isset($described['dimension'])) {
        $results['dimension'] = [
          'label' => $this->t('Dimension'),
          'info' => $described['dimension'],
        ];
      }
      if (isset($described['spec']['serverless']['region'])) {
        $results['region'] = [
          'label' => $this->t('Region'),
          'info' => $described['spec']['serverless']['region'],
        ];
      }
      if (isset($described['spec']['serverless']['cloud'])) {
        $results['cloud'] = [
          'label' => $this->t('Cloud provider'),
          'info' => $described['spec']['serverless']['cloud'],
        ];
      }
      $index_stats = $this->getClient()->getIndexStats($database_settings['database_name']);
      if (!empty($index_stats['namespaces'])) {

        // Output stats on all namespaces.
        $count = 1;
        foreach ($index_stats['namespaces'] as $namespace => $details) {
          $results['namespace_' . $count] = [
            'label' => $this->t('Namespace "@namespace"', [
              '@namespace' => $namespace,
            ]),
            'info' => $this->t('Count: @count', [
              '@count' => $details['vectorCount'],
            ]),
          ];
          $count++;
        }

        // Stats on the current namespace if not set.
        if (!isset($index_stats['namespaces'][$database_settings['collection']])) {
          $results['namespace_current'] = [
            'label' => $this->t('Namespace "@namespace"', [
              '@namespace' => $database_settings['collection'],
            ]),
            'info' => $this->t('Count: @count', [
              '@count' => 0,
            ]),
          ];
        }
      }
      if (isset($index_stats['indexFullness'])) {
        $results['index_fullness'] = [
          'label' => $this->t('Index fullness'),
          'info' => $index_stats['indexFullness'] . '%',
        ];
      }
      if (isset($index_stats['totalVectorCount'])) {
        $results['index_total_vector_count'] = [
          'label' => $this->t('Total vector count'),
          'info' => $index_stats['totalVectorCount'],
        ];
      }
    }
    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function ping(): bool {
    try {
      // If the API call fails, an exception will be thrown. If there are no
      // indexes, this will still succeed, which is fine.
      $this->getClient()->listIndexes();
      return TRUE;
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isSetup(): bool {
    if ($this->getConfig()->get('api_key')) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function createCollection(
    string $collection_name,
    int $dimension,
    VdbSimilarityMetrics $metric_type = VdbSimilarityMetrics::CosineSimilarity,
    string $database = 'default',
  ): void {
    // Pinecone serverless does not require the use of Collections and it is
    // not available at all in the starter, so we skip that for our basic
    // integration.
  }

  /**
   * {@inheritdoc}
   */
  public function getCollections(string $database = 'default'): array {
    // No support for collections at this time, Pinecone serverless only.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function dropCollection(
    string $collection_name,
    string $database = 'default',
  ): void {
    // No support for collections at this time, Pinecone serverless only.
  }

  /**
   * {@inheritdoc}
   */
  public function insertIntoCollection(
    string $collection_name,
    array $data,
    string $database = 'default',
  ): void {
    // We support Pinecone serverless only, namespaces are our pseudo
    // collections in this integration.
    $this->getClient()->insertIntoNamespace($collection_name, $data, $database);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteFromCollection(
    string $collection_name,
    array $ids,
    string $database = 'default',
  ): void {
    // We support Pinecone serverless only, namespaces are our pseudo
    // collections in this integration.
    $this->getClient()->deleteFromNamespace($collection_name, $ids, $database);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAllItems(array $configuration, $datasource_id = NULL): void {
    $this->getClient()->deleteAllFromNamespace(
      namespace: $configuration['database_settings']['collection'],
      index_name: $configuration['database_settings']['database_name'],
    );
  }

  /**
   * {@inheritdoc}
   */
  public function deleteItems(array $configuration, array $item_ids): void {
    $vdbIds = $this->getVdbIds(
      collection_name: $configuration['database_settings']['collection'],
      drupalIds: $item_ids,
      database: $configuration['database_settings']['database_name'],
    );
    if ($vdbIds) {
      $this->getClient()->deleteFromNamespace(
        namespace: $configuration['database_settings']['collection'],
        ids: $vdbIds,
        index_name: $configuration['database_settings']['database_name'],
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function fetch(
    string $collection_name,
    array $ids,
    string $database = 'default',
  ): array {
    // This fetches via the Drupal IDs.
    return $this->getClient()->fetch($collection_name, $ids, $database);
  }

  /**
   * {@inheritdoc}
   */
  public function getVdbIds(
    string $collection_name,
    array $drupalIds,
    string $database = 'default',
  ): array {
    // This gets the Pinecone IDs from the Drupal IDs.
    $data = $this->fetch(
      collection_name: $collection_name,
      ids: $drupalIds,
      database: $database,
    );
    $ids = [];
    if (!empty($data)) {
      foreach ($data as $item) {
        $ids[] = $item['id'];
      }
    }
    return $ids;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareFilters(QueryInterface $query): mixed {
    $index = $query->getIndex();
    $condition_group = $query->getConditionGroup();

    // Process filters, including handling nested groups.
    $filters = $this->processConditionGroup($index, $condition_group);

    // Combine all filters with $and if there are multiple conditions.
    if ($filters) {
      return count($filters) > 1 ? ['$and' => $filters] : $filters[0];
    }

    return [];
  }

  /**
   * Processes a condition group, including handling nested condition groups.
   */
  private function processConditionGroup($index, ConditionGroupInterface $condition_group): array {
    $filters = [];

    foreach ($condition_group->getConditions() as $condition) {
      // Check if the current condition is actually a nested ConditionGroup.
      if ($condition instanceof ConditionGroupInterface) {
        // Recursively process the nested ConditionGroup.
        $nestedFilters = $this->processConditionGroup($index, $condition);
        if ($nestedFilters) {
          // Add the nested filters as a grouped condition.
          $filters[] = ['$and' => $nestedFilters];
        }
      }
      else {
        $fieldData = $index->getField($condition->getField());

        // Only apply filters to fields that exist on the index.
        if ($fieldData === NULL) {
          continue;
        }
        $isMultiple = $fieldData ? $this->isMultiple($fieldData) : FALSE;
        $values = is_array($condition->getValue()) ? $condition->getValue() : [$condition->getValue()];
        $filter = [];

        // Handle multiple values fields.
        if ($isMultiple) {
          if (in_array($condition->getOperator(), ['=', 'IN'])) {
            // Use $in for Pinecone if the operator is '=' or 'IN'.
            $filter[$condition->getField()] = ['$in' => $values];
          }
          else {
            $this->messenger->addWarning('Pinecone does not support negative operator on multiple fields.');
          }
        }
        else {
          // Handle single value fields based on the operator.
          switch ($condition->getOperator()) {
            case '=':
              $filter[$condition->getField()] = ['$eq' => $values[0]];
              break;

            case '!=':
              $filter[$condition->getField()] = ['$ne' => $values[0]];
              break;

            case '>':
              $filter[$condition->getField()] = ['$gt' => $values[0]];
              break;

            case '>=':
              $filter[$condition->getField()] = ['$gte' => $values[0]];
              break;

            case '<':
              $filter[$condition->getField()] = ['$lt' => $values[0]];
              break;

            case '<=':
              $filter[$condition->getField()] = ['$lte' => $values[0]];
              break;

            case 'IN':
              $filter[$condition->getField()] = ['$in' => $values];
              break;

            case 'NOT IN':
              $filter[$condition->getField()] = ['$nin' => $values];
              break;

            default:
              // If the operator is not supported, log a warning.
              $this->messenger->addWarning('Operator @operator is not supported by Pinecone.', [
                '@operator' => $condition->getOperator(),
              ]);
              break;
          }
        }

        // Add the prepared filter to the list.
        if ($filter) {
          $filters[] = $filter;
        }
      }
    }

    return $filters;
  }

  /**
   * {@inheritdoc}
   */
  public function querySearch(
    string $collection_name,
    array $output_fields,
    mixed $filters = [],
    int $limit = 10,
    int $offset = 0,
    string $database = 'default',
  ): array {
    // Pinecone requires that either a vector or an ID is provided. No results
    // will be returned from this method ever. The ::vectorSearch()
    // method should be used if a Vector is provided and the ::fetch() method
    // should be used to fetch IDs.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function vectorSearch(
    string $collection_name,
    array $vector_input,
    array $output_fields,
    QueryInterface $query,
    mixed $filters = [],
    int $limit = 10,
    int $offset = 0,
    string $database = 'default',
  ): array {
    // Pinecone requires that either a vector or an ID is provided. No results
    // will be returned if there are no filters provided.
    if (empty($vector_input)) {
      return [];
    }
    $matches = $this->getClient()->query(
      namespace: $collection_name,
      index_name: $database,
      filter: $filters,
      topK: $limit,
      vector: $vector_input,
    );

    // Normalize the results to match what other VDB Providers return.
    $results = [];
    foreach ($matches as $match) {
      $results[] = $match['metadata'] + ['distance' => $match['score'], 'id' => $match['id']];
    }
    return $results;
  }

}
