<?php

namespace Drupal\elasticsearch_connector\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Class PrepareSearchQueryEvent
 *
 * @package Drupal\elasticsearch_connector\Event
 */
class PrepareSearchQueryEvent extends Event {

  const PREPARE_QUERY = 'elasticsearch_connector.prepare_searchquery';

  protected $elasticSearchQuery;
  protected $indexName;

  /**
   * PrepareSearchQueryEvent constructor.
   *
   * @param $elasticSearchQuery
   * @param $indexName
   */
  public function __construct($elasticSearchQuery, $indexName) {
    $this->elasticSearchQuery = $elasticSearchQuery;
    $this->indexName = $indexName;
  }

  /**
   * Getter for the elasticSearchQuery config array.
   *
   * @return elasticSearchQuery
   */
  public function getElasticSearchQuery() {
    return $this->elasticSearchQuery;
  }

  /**
   * Setter for the elasticSearchQuery config array.
   *
   * @param $elasticSearchQuery
   */
  public function setElasticSearchQuery($elasticSearchQuery) {
    $this->elasticSearchQuery = $elasticSearchQuery;
  }

  /**
   * Getter for the index name.
   *
   * @return indexName
   */
  public function getIndexName() {
    return $this->indexName;
  }
}
