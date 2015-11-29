<?php

/**
 * Lets modules alter the index creation.
 *
 * @param array $params
 * @param object $cluster
 * @param object $client
 */
function hook_elasticsearch_connector_add_index_alter(&$params, $cluster, $client) {

}

/**
 * Lets modules alter the index update.
 *
 * @param array $params
 * @param object $cluster
 * @param object $client
 */
function hook_elasticsearch_connector_update_index_alter(&$params, $cluster, $client) {

}

/**
 * Lets modules alter available clusters.
 *
 * @param array $clusters
 */
function hook_elasticsearch_connector_clusters_alter($clusters) {

}
