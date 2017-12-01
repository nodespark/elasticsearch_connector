<?php

namespace Drupal\elasticsearch_connector\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Class PrepareIndexEvent
 *
 * @package Drupal\elasticsearch_connector\Event
 */
class PrepareIndexEvent extends Event {

  const PREPARE_INDEX = 'elasticsearch_connector.prepare_index';

  protected $indexConfig;
  protected $indexName;

  /**
   * PrepareIndexEvent constructor.
   *
   * @param $indexConfig
   * @param $indexName
   */
  public function __construct($indexConfig, $indexName) {
    $this->indexConfig = $indexConfig;
    $this->indexName = $indexName;
  }

  /**
   * Getter for the index config array.
   *
   * @return indexConfig
   */
  public function getIndexConfig() {
    return $this->indexConfig;
  }

  /**
   * Setter for the index config array.
   *
   * @param $indexConfig
   */
  public function setIndexConfig($indexConfig) {
    $this->indexConfig = $indexConfig;
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
