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

class SearchApiElasticsearchConnectorStatsException extends Exception {}

class SearchApiElasticsearchConnectorStats {

  // Define the TTL constant of the stats.
  const TTL = '365d';
  const TYPE_EXT = '_stats';

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
   * @param SearchApiElasticsearchConnector $server
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
   * @param string $ttl
   * @return array
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
      '_ttl' => array(
        'enabled' => TRUE,
        'default' => $this->ttl,
      ),
      'properties' => array(
        'server_name' => array(
          'type' => 'string',
          'index' => 'not_analyzed'
        ),
        'index_name' => array(
          'type' => 'string',
          'index' => 'not_analyzed'
        ),
        'keywords_original' => array(
          'type' => 'string',
          'index' => 'not_analyzed'
        ),
        'keywords' => array(
          'type' => 'string',
          'index' => 'not_analyzed'
        ),
        'filters' => array(
          'type' => 'string',
          'index' => 'not_analyzed'
        ),
        'sort' => array(
          'type' => 'string',
          'index' => 'not_analyzed'
        ),
        'uid' => array(
          'type' => 'long',
        ),
        'results' => array(
          'type' => 'long',
        ),
        'username' => array(
          'type' => 'string',
          'index' => 'not_analyzed'
        ),
        'timestamp' => array(
          'type' => 'date',
        ),
      )
    );

    return $my_type_mapping;
  }

  /**
   * Create Elasticsearch connector search api statistics type.
   *
   * @param string
   * @patam string
   * @return array
   */
  protected function createStatsType($params) {
    $client = $this->connector->getConnectorObject();
    $params['body'][$params['type']] = $this->getStatsMapping();
    try {
      return $client->indices()->putMapping(
          $params
      );
    }
    catch (Exception $e) {
      throw $e;
    }
  }

  /**
   * Logging the statistics into the stats type in elasticsearch index.
   *
   * @param \Elasticsearch\Client $client
   * @param array $access_log
   */
  public function logStat($response) {
    global $user;

    // Skip the log if the results matched.
    if (!empty($this->index->options['log_only_not_found']) && !empty($response['hits']['total'])) {
      return;
    }

    $client = $this->connector->getConnectorObject();
    $ret = FALSE;
    $doc = $this->connector->getIndexParam($this->index, TRUE);
    $doc['type'] .= self::TYPE_EXT;
    // Indexing document.

    try {
      if (!$client->indices()->existsType(array('index' => $doc['index'], 'type' => $doc['type']))) {
        $this->createStatsType($doc);
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
        'timestamp'         => time(),
        '_ttl'              => $this->ttl,
      );

      $ret = $client->index($doc);
      return $ret;
    }
    catch (Exception $e) {
      watchdog('elasticsearch_connector_statistics', $e->getMessage(), array(), WATCHDOG_ERROR);
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