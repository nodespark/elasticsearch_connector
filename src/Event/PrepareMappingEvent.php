<?php

namespace Drupal\elasticsearch_connector\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Class PrepareMappingEvent
 *
 * @package Drupal\elasticsearch_connector\Event
 */
class PrepareMappingEvent extends Event {

  const PREPARE_MAPPING = 'elasticsearch_connector.prepare_mapping';

  protected $mappingConfig;
  protected $type;
  protected $field;

  /**
   * PrepareMappingEvent constructor.
   *
   * @param $mappingConfig
   * @param $type
   * @param $field
   */
  public function __construct($mappingConfig, $type, $field) {
    $this->mappingConfig = $mappingConfig;
    $this->type = $type;
    $this->field = $field;
  }

  /**
   * Getter for the mapping config array.
   *
   * @return mappingConfig
   */
  public function getMappingConfig() {
    return $this->mappingConfig;
  }

  /**
   * Setter for the mapping config array.
   *
   * @param $mappingConfig
   */
  public function setMappingConfig($mappingConfig) {
    $this->mappingConfig = $mappingConfig;
  }

  /**
   * Getter for the mapping type.
   *
   * @return type
   */
  public function getMappingType() {
    return $this->type;
  }

  /**
   * Getter for the field.
   *
   * @return field
   */
  public function getMappingField() {
    return $this->field;
  }

}
