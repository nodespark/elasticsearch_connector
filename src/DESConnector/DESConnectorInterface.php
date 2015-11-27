<?php
/**
 * @file
 * Provides Elasticsearch interface for Drupal's Elasticsearch Connector module.
 */

namespace Drupal\elasticsearch_connector\DESConnector;

/**
 * Drupal Elasticsearch Interface.
 *
 * @package Drupal\elasticsearch_connector
 */
interface DESConnectorInterface {
  static function getInstance(array $hosts);
}
