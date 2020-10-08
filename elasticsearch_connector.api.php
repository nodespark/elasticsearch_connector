<?php

/**
 * @file
 * Elasticsearch connector api.
 */

use Drupal\elasticsearch_connector\Entity\Cluster;

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
function hook_elasticsearch_connector_load_library_options_alter(array &$options, Cluster $cluster) {
}

/**
 * Modify the list of supported data types.
 *
 * @param array &$data_types
 *   Array of strings representing supported data types.
 */
function hook_elasticsearch_connector_supported_data_types_alter(array &$data_types) {

}
