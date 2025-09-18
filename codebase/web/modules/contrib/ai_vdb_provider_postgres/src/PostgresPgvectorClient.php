<?php

namespace Drupal\ai_vdb_provider_postgres;

use Drupal\ai\Enum\VdbSimilarityMetrics;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\ai_vdb_provider_postgres\Exception\AddFieldIfNotExistsException;
use Drupal\ai_vdb_provider_postgres\Exception\CreateCollectionException;
use Drupal\ai_vdb_provider_postgres\Exception\DatabaseConnectionException;
use Drupal\ai_vdb_provider_postgres\Exception\DeleteFromCollectionException;
use Drupal\ai_vdb_provider_postgres\Exception\DropCollectionException;
use Drupal\ai_vdb_provider_postgres\Exception\EscapeStringException;
use Drupal\ai_vdb_provider_postgres\Exception\GetCollectionsException;
use Drupal\ai_vdb_provider_postgres\Exception\InsertIntoCollectionException;
use Drupal\ai_vdb_provider_postgres\Exception\QuerySearchException;
use Drupal\ai_vdb_provider_postgres\Exception\VectorSearchException;
use Drupal\ai_vdb_provider_postgres\Plugin\VdbProvider\PostgresProvider;
use PgSql;
use PgSql\Connection;

/**
 * Provides abstracted Postgres client to interface with pgvector.
 */
class PostgresPgvectorClient {

  protected const DATA_TYPE_MAPPING = [
    'integer' => 'INTEGER',
    'text' => 'TEXT',
    // Use BIGINT instead of TIMESTAMP because at index time, the provider
    // does not know whether the field value is a date or number.
    'date' => 'BIGINT',
    'decimal' => 'DECIMAL',
    'string' => 'VARCHAR',
    'boolean' => 'BOOLEAN',
  ];

  /**
   * Get the Postgres database connection.
   *
   * @return PgSql\Connection|FALSE
   *   A connection to the Postgres database.
   *
   * @throws \Drupal\ai_vdb_provider_postgres\Exception\DatabaseConnectionException
   */
  public function getConnection(
    string $host,
    int $port,
    string $username,
    string $password,
    string $default_database,
    string $database = NULL
  ): Connection|FALSE {
    if (!isset($database)) {
      $database = $default_database;
    }
    $connection = pg_connect(
      connection_string: "host={$host} dbname={$database} port={$port} user={$username} password={$password}"
    );
    if (!$connection) {
      throw new DatabaseConnectionException(
        message: 'Cannot connect to Postgres database using provided connection details',
      );
    }
    return $connection;
  }

  /**
   * {@inheritdoc}
   */
  public function ping(Connection $connection): bool {
    return pg_ping(connection: $connection);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\ai_vdb_provider_postgres\Exception\GetCollectionsException
   */
  public function getCollections(Connection $connection): array {
    $result = pg_query_params(
      connection: $connection,
      query: 'SELECT * FROM pg_catalog.pg_tables WHERE schemaname != $1 AND schemaname != $2;',
      params: ['pg_catalog', 'information_schema'],
    );
    if (!$result) {
      throw new GetCollectionsException(message: pg_last_error(connection: $connection));
    }
    $rows = pg_fetch_all(result: $result);

    $tables = array_map(
      callback: function ($row) {
        return $row['tablename'];
      },
      array: $rows
    );
    return $tables;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\ai_vdb_provider_postgres\Exception\CreateCollectionException
   */
  public function createCollection(
    string     $collection_name,
    int        $dimension,
    Connection $connection,
  ): void {
    $escaped_collection_name = $this->escapeIdentifierForSql(
      identifier_to_escape: $collection_name,
      connection: $connection,
    );
    $result = pg_query(
      connection: $connection,
      query: "CREATE TABLE {$escaped_collection_name} (id bigserial PRIMARY KEY, content VARCHAR, drupal_entity_id VARCHAR, drupal_long_id VARCHAR, server_id VARCHAR, index_id VARCHAR, embedding vector({$dimension}));"
    );
    if (!$result) {
      throw new CreateCollectionException(message: pg_last_error(connection: $connection));
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\ai_vdb_provider_postgres\Exception\DropCollectionException
   */
  public function dropCollection(
    string     $collection_name,
    Connection $connection,
  ): void {
    $escaped_collection_name = $this->escapeIdentifierForSql(
      identifier_to_escape: $collection_name,
      connection: $connection,
    );
    $result = pg_query(
      connection: $connection,
      query: "DROP TABLE IF EXISTS {$escaped_collection_name} CASCADE;"
    );
    if (!$result) {
      throw new DropCollectionException(message: pg_last_error(connection: $connection));
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\ai_vdb_provider_postgres\Exception\EscapeStringException
   * @throws \Drupal\ai_vdb_provider_postgres\Exception\InsertIntoCollectionException
   */
  public function insertIntoCollection(
    string     $collection_name,
    array     $drupal_entity_id,
    array     $drupal_long_id,
    array     $content,
    array      $vector,
    array      $server_id,
    array       $index_id,
    array      $extra_fields,
    Connection $connection
  ): void {
    $vector_string = $this->prepareVectorArrayForSql(
      vector: $vector['value'],
      connection: $connection,
    );
    $escaped_collection_name = $this->escapeIdentifierForSql(
      identifier_to_escape: $collection_name,
      connection: $connection,
    );
    // Prepare columns and values for extra fields.
    $extra_fields_columns = '';
    $extra_fields_values = '';
    $extra_fields_params = [];

    $relation_queries = [];

    $param_index = 6;
    foreach ($extra_fields as $field_name => $field_data) {
      if ($field_data['is_multiple']) {
        if ($relation_query = $this->prepareRelationQuery($collection_name, $field_name, $field_data, $connection)) {
          $relation_queries[] = $relation_query;
        }
      } else {
        $extra_fields_columns .= ", {$field_name}";
        $extra_fields_values .= ", \${$param_index}";
        $extra_fields_params[] = $field_data['value'];
        $param_index++;
      }
    }
    $main_query = "INSERT INTO {$escaped_collection_name} (content, drupal_entity_id, drupal_long_id, server_id, index_id, embedding{$extra_fields_columns}) VALUES ($1, $2, $3, $4, $5, {$vector_string}{$extra_fields_values});";

    $params = array_merge([
      $content['value'],
      $drupal_entity_id['value'],
      $drupal_long_id['value'],
      $server_id['value'],
      $index_id['value'],
    ], $extra_fields_params);

    $result = pg_query_params(
      connection: $connection,
      query: $main_query,
      params: $params,
    );
    if (!$result) {
      throw new InsertIntoCollectionException(message: pg_last_error(connection: $connection));
    }
    foreach ($relation_queries as $relation_query) {
      $result = pg_query(
        connection: $connection,
        query: $relation_query
      );
      if (!$result) {
        throw new InsertIntoCollectionException(message: pg_last_error(connection: $connection));
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\ai_vdb_provider_postgres\Exception\DeleteFromCollectionException
   * @throws \Drupal\ai_vdb_provider_postgres\Exception\EscapeStringException
   */
  public function deleteFromCollection(
    string     $collection_name,
    array      $ids,
    Connection $connection,
  ): void {
    if (empty($ids)) {
      return;
    }
    $escaped_collection_name = $this->escapeIdentifierForSql(
      identifier_to_escape: $collection_name,
      connection: $connection,
    );
    $prepared_ids = $this->prepareStringArrayForSql(items: $ids, connection: $connection);
    $result = pg_query(
      connection: $connection,
      query: "DELETE FROM {$escaped_collection_name} WHERE drupal_entity_id IN {$prepared_ids};"
    );
    if (!$result) {
      throw new DeleteFromCollectionException(message: pg_last_error(connection: $connection));
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\ai_vdb_provider_postgres\Exception\EscapeStringException
   * @throws \Drupal\ai_vdb_provider_postgres\Exception\QuerySearchException
   */
  public function querySearch(
    string     $collection_name,
    array      $output_fields,
    string     $filters,
    int        $limit,
    int        $offset,
    Connection $connection,
  ): array {
    $escaped_collection_name = $this->escapeIdentifierForSql(
      identifier_to_escape: $collection_name,
      connection: $connection,
    );
    $prepared_output_fields = $this->prepareFieldArrayForSql(fields: $output_fields, connection: $connection, collection_name: $collection_name);
    if (empty($filters)) {
      $query = "SELECT {$prepared_output_fields} FROM {$escaped_collection_name} LIMIT {$limit} OFFSET {$offset};";
    }
    else {
      $query = "SELECT {$prepared_output_fields} FROM {$escaped_collection_name} {$filters} LIMIT {$limit} OFFSET {$offset};";
    }
    $result = pg_query(connection: $connection, query: $query);
    if (!$result) {
      throw new QuerySearchException(message: pg_last_error(connection: $connection));
    }
    return pg_fetch_all(result: $result);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\ai_vdb_provider_postgres\Exception\EscapeStringException
   * @throws \Drupal\ai_vdb_provider_postgres\Exception\VectorSearchException
   */
  public function vectorSearch(
    string               $collection_name,
    array                $vector_input,
    array                $output_fields,
    string               $filters,
    int                  $limit,
    int                  $offset,
    VdbSimilarityMetrics $metric_type,
    Connection           $connection
  ): array {
    $metric_name = match ($metric_type) {
      VdbSimilarityMetrics::EuclideanDistance => '<->',
      VdbSimilarityMetrics::CosineSimilarity => '<=>',
      VdbSimilarityMetrics::InnerProduct => '<#>',
    };
    $escaped_collection_name = $this->escapeIdentifierForSql(
      identifier_to_escape: $collection_name,
      connection: $connection,
    );
    $prepared_output_fields = $this->prepareFieldArrayForSql(fields: $output_fields, connection: $connection, collection_name: $collection_name);
    $vectors = $this->prepareVectorArrayForSql(vector: $vector_input, connection: $connection);
    // Escape the output fields.
    $escaped_outfield_fields = array_map(
      callback: function ($field) use ($connection) {
        return $this->escapeIdentifierForSql(identifier_to_escape: $field, connection: $connection);
      },
      array: $output_fields
    );
    $outfield_fields = implode(',', $escaped_outfield_fields);
    $alias = 'subquery';
    if (empty($filters)) {
      // CosineSimilarity requires a special query.
      if ($metric_type === VdbSimilarityMetrics::CosineSimilarity) {
        $query = "SELECT (1-{$alias}.real_distance) as distance, {$outfield_fields} FROM (SELECT embedding {$metric_name} {$vectors} as real_distance, {$prepared_output_fields} FROM {$escaped_collection_name}) as {$alias} ORDER BY distance DESC LIMIT {$limit} OFFSET {$offset};";
      }
      else {
        $query = "SELECT embedding {$metric_name} {$vectors} as distance, {$prepared_output_fields} FROM {$escaped_collection_name} ORDER BY distance LIMIT {$limit} OFFSET {$offset};";
      }
    }
    else {
      if ($metric_type === VdbSimilarityMetrics::CosineSimilarity) {
        $query = "SELECT (1-{$alias}.real_distance) as distance, {$outfield_fields} FROM (SELECT embedding {$metric_name} {$vectors} as real_distance, {$prepared_output_fields} FROM {$escaped_collection_name} {$filters}) as {$alias} ORDER BY distance DESC LIMIT {$limit} OFFSET {$offset};";
      }
      else {
        $query = "SELECT embedding {$metric_name} {$vectors} as distance, {$prepared_output_fields} FROM {$escaped_collection_name} {$filters} ORDER BY distance LIMIT {$limit} OFFSET {$offset};";
      }
    }
    $result = pg_query(connection: $connection, query: $query);
    if (!$result) {
      throw new VectorSearchException(message: pg_last_error(connection: $connection));
    }
    return pg_fetch_all(result: $result);
  }


  /**
   * Transform an array of field identifier strings for use in a SQL statement.
   *
   * @param array $fields
   *   Field array.
   * @param Connection $connection
   *   The Postgres connection.
   *
   * @return string
   *   Array formatted as a field string.
   *   Eg: 'id,drupal_entity_id,drupal_long_id'
   *
   * @throws \Drupal\ai_vdb_provider_postgres\Exception\EscapeStringException
   */
  public function prepareFieldArrayForSql(array $fields, Connection $connection, $collection_name = NULL): string {
    if (empty($fields)) {
      return '';
    }
    $array_formatted_as_string = '';
    $last_element = end(array: $fields);
    foreach ($fields as $field) {
      if ($collection_name) {
        $array_formatted_as_string .= $this->escapeIdentifierForSql(identifier_to_escape: $collection_name, connection: $connection) . '.';
      }
      if ($field === $last_element) {
        $array_formatted_as_string .=
          $this->escapeIdentifierForSql(identifier_to_escape: $field, connection: $connection) . '';
        break;
      }
      $array_formatted_as_string .=
        $this->escapeIdentifierForSql(identifier_to_escape: $field, connection: $connection) . ',';
    }
    return $array_formatted_as_string;
  }

  /**
   * Transform an array of vectors to string for use in a SQL statement.
   *
   * @param array $vector
   *   Vector array.
   *   Normally an array of floats.
   * @param Connection $connection
   *   The Postgres connection.
   *
   * @return string
   *   Array formatted as a string.
   *   Eg: '[1.22424,-2.12312,-1.34654]'
   *
   * @throws \Drupal\ai_vdb_provider_postgres\Exception\EscapeStringException
   */
  public function prepareVectorArrayForSql(array $vector, Connection $connection): string {
    $array_formatted_as_string = '[' . implode(separator: ',', array: $vector) . ']';
    return $this->escapeStringForSql(string_to_escape: $array_formatted_as_string, connection: $connection);
  }

  /**
   * Transform an array of non-string data to string for use in a SQL statement.
   *
   * @param array $items
   *   An array of string items.
   *
   * @return string
   *   Array formatted as a string for SQL.
   *   Eg: "('first item', 'second item', 'third item')"
   */
  public function prepareArrayForSql(array $items): string {
    return '(' . implode(separator: ',', array: $items) . ')';
  }

  /**
   * Transform an array of strings to string for use in a SQL statement.
   *
   * @param array $items
   *   An array of string items.
   * @param Connection $connection
   *   The Postgres connection.
   *
   * @return string
   *   Array of strings formatted as a string for SQL.
   *   Eg: "('first item', 'second item', 'third item')"
   *
   * @throws \Drupal\ai_vdb_provider_postgres\Exception\EscapeStringException
   */
  public function prepareStringArrayForSql(array $items, Connection $connection): string {
    $escaped_strings = [];
    foreach ($items as $item) {
      $escaped_strings[] = $this->escapeStringForSql(string_to_escape: $item, connection: $connection);
    }
    return '(' . implode(separator: ',', array: $escaped_strings) . ')';
  }

  /**
   * Escape a string for use in a Postgres SQL statement.
   *
   * @param string $string_to_escape
   *   The string to escape.
   * @param Connection $connection
   *   The Postgres connection.
   *
   * @return string
   *   A string containing the escaped data.
   *
   * @throws \Drupal\ai_vdb_provider_postgres\Exception\EscapeStringException
   */
  private function escapeStringForSql(string $string_to_escape, Connection $connection): string {
    $result = pg_escape_literal(connection: $connection, string: $string_to_escape);
    if (!$result) {
      throw new EscapeStringException(message: pg_last_error(connection: $connection));
    }
    return $result;
  }

  /**
   * Escape a string identifier for use in a postgres SQL statement.
   *
   * @param string $identifier_to_escape
   *   The string identifier to escape.
   * @param Connection $connection
   *   The Postgres connection.
   *
   * @return string
   *   A string containing the escaped data.
   *
   * @throws \Drupal\ai_vdb_provider_postgres\Exception\EscapeStringException
   */
  public function escapeIdentifierForSql(string $identifier_to_escape, Connection $connection): string {
    $result = pg_escape_identifier(connection: $connection, string: $identifier_to_escape);
    if (!$result) {
      throw new EscapeStringException(message: pg_last_error(connection: $connection));
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\ai_vdb_provider_postgres\Exception\EscapeStringException
   * @throws \Drupal\ai_vdb_provider_postgres\Exception\AddFieldIfNotExistsException
   */
  public function updateFields($fields, string $collection_name, Connection $connection): void {
    foreach ($fields as $field) {
      $field_data_definition = $field->getDataDefinition();

      // Make assumption of basic data type if we can't get more info.
      if (!method_exists($field_data_definition, 'getFieldDefinition')) {
        $this->addFieldIfNotExists(FALSE, 'string', $field->getFieldIdentifier(), $collection_name, $connection);
        continue;
      }
      $isMultiple = TRUE;

      $field_definition = $field_data_definition->getFieldDefinition();
      if ($field_definition instanceof BaseFieldDefinition) {
        $field_cardinality = $field_definition->getCardinality();
      }
      else {
        $field_cardinality =
          $field_definition->get('fieldStorage')->getCardinality();
      }
      if ($field_cardinality === 1) {
        $isMultiple = FALSE;
      }
      $this->addFieldIfNotExists($isMultiple, $field->getType(), $field->getFieldIdentifier(), $collection_name, $connection);
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\ai_vdb_provider_postgres\Exception\EscapeStringException
   * @throws \Drupal\ai_vdb_provider_postgres\Exception\AddFieldIfNotExistsException
   */
  protected function addFieldIfNotExists(bool $isMultiple, string $data_type, string $name, string $collection_name, Connection $connection): void {
    $escaped_collection_name = $this->escapeIdentifierForSql(
      identifier_to_escape: $collection_name,
      connection: $connection,
    );
    $postgres_type = self::DATA_TYPE_MAPPING[$data_type];
    $escaped_field_name = $this->escapeIdentifierForSql($name, $connection);


    // If isMultiple is true, create a new relationship table
    if ($isMultiple) {
      $relation_table = $this->getRelationTableName($collection_name, $name, $connection);
      $create_relation_table = "CREATE TABLE IF NOT EXISTS {$relation_table} (id SERIAL PRIMARY KEY, value {$postgres_type} NOT NULL, chunk_id INT NOT NULL, FOREIGN KEY(chunk_id) REFERENCES {$escaped_collection_name}(id) ON DELETE CASCADE);";
      $result = pg_query(connection: $connection, query: $create_relation_table);
      if (!$result) {
        throw new AddFieldIfNotExistsException(message: pg_last_error(connection: $connection));
      }
    } else {
      $query = "ALTER TABLE {$escaped_collection_name} ADD COLUMN IF NOT EXISTS {$escaped_field_name} {$postgres_type};";
      $result = pg_query(connection: $connection, query: $query);
      if (!$result) {
        throw new AddFieldIfNotExistsException(message: pg_last_error(connection: $connection));
      }
    }
  }

  protected function prepareRelationQuery($collection_name, $field_name, $field_data, $connection) {
    $query = '';
    $escaped_collection_name_id_sequence = $this->escapeIdentifierForSql(
      identifier_to_escape: "{$collection_name}_id_seq",
      connection: $connection,
    );
    // Prepare entries for relation table.
    $relation_table_fields = [];
    $escaped_relation_table_name = $this->getRelationTableName($collection_name, $field_name, $connection);
    if (!is_array($field_data['value'])) {
      $field_data['value'] = [$field_data['value']];
    }
    foreach ($field_data['value'] as $value) {
      if (empty($value)) {
        continue;
      }
      $relation_table_fields[$escaped_relation_table_name][] = $value;
    }

    foreach ($relation_table_fields as $escaped_relation_table_name => $field_values) {
      $query .= "INSERT INTO {$escaped_relation_table_name} (value, chunk_id) values ";
      $last_value = end($field_values);
      foreach ($field_values as $field_value) {
        $query .= "({$field_value}, currval('{$escaped_collection_name_id_sequence}'))";
        if ($field_value === $last_value) {
          $query .= ';';
        }
        else {
          $query .= ",";
        }
      }
    }
    return $query;
  }

  public function getRelationTableName($collection_name, $field_name, $connection): string {
    return $this->escapeIdentifierForSql(
      identifier_to_escape: "{$collection_name}__{$field_name}",
      connection: $connection,
    );
  }
}
