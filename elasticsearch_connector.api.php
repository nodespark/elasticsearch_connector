<?php

/**
 * @file
 * Hooks provided by the ElasticSearch Connector module.
 */

/**
 * Modify the connector library options.
 *
 * @param array $options
 *   Library options.
 * @param \Drupal\elasticsearch_connector\Entity\Cluster $cluster
 *   Cluster entity.
 */
function hook_elasticsearch_connector_load_library_options_alter(array &$options, \Drupal\elasticsearch_connector\Entity\Cluster $cluster) {
}
