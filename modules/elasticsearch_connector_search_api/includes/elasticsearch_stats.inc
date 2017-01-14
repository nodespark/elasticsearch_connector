<?php
/**
 *
 * @author nikolayignatov
 * @file
 * Building the logic required for storing the showing search statistics.
 *
 * TODO: Stats:
 *  No found results.
 *  Return how many search queries has been made.
 *  If the user used suggestion.
 *  If no results, does the user make another search with other words.
 *  Filters used.
 *  Sorting used.
 *
 *
 */

// TODO: Use time based indexes and format, suitable for Kibana!
// TODO: Use settings for time based indexes.
// TODO: Maybe we should remove the statistics when Search API index has been deleted.

class SearchApiElasticsearchConnectorStatsException extends Exception {}

class SearchApiElasticsearchConnectorStats {

  // Define the TTL constant of the stats.
  const NUM_OF_SHARDS = 1;
  const NUM_OF_REPLICAS = 0;
  const TYPE_EXT = 'stats';

  // The server object container.
  private $server;
  // The index object container.
  private $index;
  // The index object container.
  private $query;
  // The elasticsearch connector object.
  private $connector;
  // The TTL of the each stat log.
  private $ttl;

  /**
   * Class constructure.
   *
   * @param SearchApiQueryInterface $query
   *   The query.
   * @param SearchApiElasticsearchConnector $connector
   *   The server connector object.
   *
   * @throws \SearchApiElasticsearchConnectorStatsException
   */
  public function __construct(SearchApiQueryInterface $query, SearchApiElasticsearchConnector $connector) {
    $this->query = $query;
    $this->connector = $connector;
    $this->index = $this->query->getIndex();
    if (!empty($this->index)) {
      $this->server = $this->index->server();
      $this->ttl = !empty($this->index->options['index_statistics_ttl']) ? $this->index->options['index_statistics_ttl'] : self::TTL;
    }

    if (empty($this->index)) {
      throw new SearchApiElasticsearchConnectorStatsException(t('Cannot get the index by query.'));
    }

    if (empty($this->server)) {
      throw new SearchApiElasticsearchConnectorStatsException(t('Cannot get the server by index.'));
    }
  }

  /**
   * Return the mapping required by the statistics module.
   *
   * @return array
   *   The mapping for the statistics document.
   */
  private function getStatsMapping() {
    // Index Mapping
    $my_type_mapping = array(
      '_source' => array(
        'enabled' => TRUE
      ),
      '_all' => array(
        'enabled' => TRUE
      ),
      'properties' => array(
        'server_name' => array(
          'type' => 'keyword',
        ),
        'index_name' => array(
          'type' => 'keyword',
        ),
        'keywords_original' => array(
          'type' => 'keyword',
        ),
        'keywords' => array(
          'type' => 'keyword',
        ),
        'filters' => array(
          'type' => 'keyword',
        ),
        'sort' => array(
          'type' => 'keyword',
        ),
        'uid' => array(
          'type' => 'long',
        ),
        'results' => array(
          'type' => 'long',
        ),
        'username' => array(
          'type' => 'keyword',
        ),
        'ip' => array(
          'type' => 'ip',
        ),
        'timestamp' => array(
          'type' => 'date',
          'format' => 'epoch_second',
        ),
      )
    );

    return $my_type_mapping;
  }

  /**
   * Create Elasticsearch connector search api statistics type.
   *
   * @param array $params
   *   The index name and the type name for the index creation.
   *
   * @return array
   *   Result of the create index execution.
   *
   * @throws \Exception
   */
  protected function createStatsIndex($params) {
    $client = $this->connector->getConnectorObject();
    $index_params = array(
      'index' => $params['index'],
      'body' => array(
        'settings' => array(
          'number_of_shards' =>
            !empty($this->index->options['index_statistics_num_of_shards']) ?
              $this->index->options['index_statistics_num_of_shards'] : self::NUM_OF_SHARDS,
          'number_of_replicas' =>
            !empty($this->index->options['index_statistics_num_of_replicas']) ?
              $this->index->options['index_statistics_num_of_replicas'] : self::NUM_OF_REPLICAS,
        ),
        'mappings' => array(
          $params['type'] => $this->getStatsMapping()
        )
      )
    );
    try {
      return $client->indices()->create(
        $index_params
      );
    }
    catch (Exception $e) {
      throw $e;
    }
  }

  /**
   * Get the index and type parameters.
   *
   * @return array
   */
  protected function getIndexParam() {
    $params = $this->connector->getIndexParam($this->index, FALSE);
    $params['index'] .= '_' . implode('_', array(self::TYPE_EXT, date('Ym')));
    $params['type'] = self::TYPE_EXT;
    return $params;
  }

  /**
   * Logging the statistics into the stats type in elasticsearch index.
   *
   * @param array $response
   *   The response array from elasticsearch client.
   *
   * @return array
   *   The result of the elasticsearch client execution.
   */
  public function logStat($response) {
    global $user;

    // Skip the log if the results matched.
    if (!empty($this->index->options['log_only_not_found']) && !empty($response['hits']['total'])) {
      return array();
    }

    $client = $this->connector->getConnectorObject();

    $doc = $this->getIndexParam();

    try {
      // Indexing document.
      if (!$client->indices()->exists(array('index' => $doc['index']))) {
        $this->createStatsIndex($doc);
      }

      $doc['body']  = array(
        'server_name'      => $this->server->machine_name,
        'index_name'       => $this->index->machine_name,
        'keywords_original' => $this->getOriginalKeys(),
        'keywords'          => $this->parseKeys($this->query->getOriginalKeys()),
        'filters'           => $this->getFilters(),
        'sort'              => $this->getSort(),
        'uid'               => $user->uid,
        'results'           => isset($response['hits']['total']) ? $response['hits']['total'] : 0,
        'username'          => ($user->uid > 0 ? $user->name : ''),
        'ip'                => ip_address(),
        'timestamp'         => time(),
      );

      $ret = $client->index($doc);
      return $ret;
    }
    catch (Exception $e) {
      watchdog('elasticsearch_connector_statistics', $e->getMessage(), array(), WATCHDOG_ERROR);
      return array();
    }
  }

  /**
   * Get the sort of the query.
   * @return string
   */
  protected function getSort() {
    // TODO: Handle $this->query->getSort() logging the sorting.
    return '';
  }

  /**
   * Get original keys to log.
   * @return string
   */
  protected function getOriginalKeys() {
    return strtolower($this->query->getOriginalKeys());
  }

  /**
   * Get filters
   */
  protected function getFilters() {
    // TODO: Handle the filters.
  }

  /**
   * {@inheritdoc}
   */
  protected function parseKeys($keys) {
    $keys = strtolower($keys);
    $keys = str_replace('"', '', $keys);
    $ret = preg_split('/\s+/u', $keys);

    return $ret;
  }
}