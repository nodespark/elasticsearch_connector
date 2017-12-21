<?php

namespace Drupal\elasticsearch_connector\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Class BuildSearchParamsEvent
 *
 * @package Drupal\elasticsearch_connector\Event
 */
class BuildSearchParamsEvent extends Event {

  const BUILD_QUERY = 'elasticsearch_connector.build_searchparams';

  protected $params;
  protected $indexName;

  /**
   * BuildSearchParamsEvent constructor.
   *
   * @param $params
   * @param $indexName
   */
  public function __construct($params, $indexName) {
    $this->params = $params;
    $this->indexName = $indexName;
  }

  /**
   * Getter for the params config array.
   *
   * @return params
   */
  public function getElasticSearchParams() {
    return $this->params;
  }

  /**
   * Setter for the params config array.
   *
   * @param $params
   */
  public function setElasticSearchParams($params) {
    $this->params = $params;
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
