<?php

namespace Drupal\elasticsearch_connector\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Class PrepareIndexMappingEvent
 *
 * @package Drupal\elasticsearch_connector\Event
 */
class PrepareIndexMappingEvent extends Event {

  const PREPARE_INDEX_MAPPING = 'elasticsearch_connector.prepare_index_mapping';

  protected $indexMappingParams;
  protected $indexName;

  /**
   * PrepareIndexMappingEvent constructor.
   *
   * @param $indexMappingParams
   * @param $indexName
   */
  public function __construct($indexMappingParams, $indexName) {
    $this->indexMappingParams = $indexMappingParams;
    $this->indexName = $indexName;
  }

  /**
   * Getter for the index params array.
   *
   * @return indexMappingParams
   */
  public function getIndexMappingParams() {
    return $this->indexMappingParams;
  }

  /**
   * Setter for the index params array.
   *
   * @param $indexMappingParams
   */
  public function setIndexMappingParams($indexMappingParams) {
    $this->indexMappingParams = $indexMappingParams;
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
