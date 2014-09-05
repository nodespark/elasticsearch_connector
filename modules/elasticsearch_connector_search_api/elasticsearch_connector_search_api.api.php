<?php

/**
 * @file
 * Hooks provided by the Elasticsearch Connector Search API module.
 */

/**
 * Lets modules alter an Elastic search request before sending it.
 *
 * @param SearchApiQueryInterface $query
 *   The SearchApiQueryInterface object representing the executed search query.
 * @param array $params
 *   An associative array containing the index being queried, the type,
 *   the fields, the query_string etc.
 */
function hook_elasticsearch_connector_search_api_query_alter($query, &$params) {

}

/**
 * Lets modules alter the search results returned from an Elastic search.
 *
 * @param array $results
 *   The results array that will be returned for the search.
 * @param SearchApiQueryInterface $query
 *   The SearchApiQueryInterface object representing the executed search query.
 * @param array $response
 *   The Elastic Search response array.
 */
function hook_elasticsearch_connector_search_api_results_alter(&$results, $query, $response) {

}

/**
 * Lets modules alter the forced index creation from SearchApi integration.
 *
 * @param SearchApiIndex $index
 * @param array $params
 */
function hook_elasticsearch_connector_search_api_add_index($index, &$params) {

}