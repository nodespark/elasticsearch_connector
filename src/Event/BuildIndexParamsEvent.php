<?php

namespace Drupal\elasticsearch_connector\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Class BuildIndexParamsEvent
 *
 * @package Drupal\elasticsearch_connector\Event
 */
class BuildIndexParamsEvent extends Event {

  const BUILD_PARAMS = 'elasticsearch_connector.build_indexparams';

  protected $params;
  protected $indexName;

  /**
   * BuildIndexParamsEvent constructor.
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
  public function getElasticIndexParams() {
    return $this->params;
  }

  /**
   * Setter for the params config array.
   *
   * @param $params
   */
  public function setElasticIndexParams($params) {
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
