<?php
/**
 * @file
* Contains the ElasticsearchConnectorSearchApiPrefixSearch class.
*/

class ElasticsearchConnectorSearchApiPrefixSearch extends SearchApiAbstractProcessor {

  /**
   * {@inheritdoc}
   */
  public function __construct(SearchApiIndex $index, array $options = array()) {
    parent::__construct($index, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function supportsIndex(SearchApiIndex $index) {
    $server = $index->server();
    if (!empty($server) && $server->class == 'search_api_elasticsearch_connector') {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessSearchQuery(SearchApiQuery $query) {
    $query->setOption(SearchApiElasticsearchConnector::PREFIX_SEARCH_FIELDS, $this->options['fields']);
    $query->setOption(SearchApiElasticsearchConnector::PREFIX_SEARCH, TRUE);
  }

}