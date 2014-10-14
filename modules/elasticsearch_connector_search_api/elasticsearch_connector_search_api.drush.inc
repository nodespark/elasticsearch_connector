<?php

/**
 * @file
 * Drush integration for the elasticsearch_connector_search_api module.
 */

/**
 * Implements hook_drush_command().
 */
function elasticsearch_connector_search_api_drush_command() {
  $items['reset-elasticsearch'] = array(
    'description' => dt('Drop and re-create a Search API Elasticsearch index.'),
    'arguments' => array(
      'index' => 'The name of the Search API index whose Elasticsearch index to re-create.',
      'number_of_shards'    => 'The new number of shards the elasticsearch index should have.',
      'number_of_replicas'  => 'The new number of replica the elasticsearch index should have.',
    ),
    'required-arguments' => TRUE,
    'options' => array(
      'yes' => 'Skip confirmation and proceed.',
    ),
  );
  return $items;
}

/**
 * Callback for the reset-elasticsearch drush command.
 */
function drush_elasticsearch_connector_search_api_reset_elasticsearch($search_api_index_name, $number_of_shards, $number_of_replicas = 0) {
  // Load the Search API index.
  $index = search_api_index_load($search_api_index_name);
  if (!$index || $index->server()->class != 'search_api_elasticsearch_connector') {
    return drush_set_error('elasticsearch_connector_search_api', t("Search API index '@index_name' not found.", array('@index_name' => $search_api_index_name)));
  }

  if ($number_of_shards != (int) $number_of_shards || $number_of_shards < 1) {
    return drush_set_error('elasticsearch_connector_search_api', t("Invalid number of shards. The param should be an integer greater than or equal to 1."));
  }

  if ($number_of_replicas != (int) $number_of_replicas || $number_of_replicas < 0) {
    return drush_set_error('elasticsearch_connector_search_api', t("Invalid number of replicas. The param should be an integer greater than or equal to 0."));
  }

  $es_index = $index->options['index_name']['index'];

  // Find the affected Search API indexes.
  $all_indexes = search_api_index_load_multiple(FALSE, array('enabled' => TRUE));
  // Always list the specified index first.
  $affected_indexes = array($index);
  foreach ($all_indexes as $index) {
    if ($index->machine_name == $search_api_index_name) {
      continue;
    }

    $server = $index->server();
    if ($server->class == 'search_api_elasticsearch_connector' && $index->options['index_name']['index'] == $es_index) {
      $affected_indexes[] = $index;
    }
  }

  // List Search API indexes this opperation will affect.
  drush_print('The following Search API indexes will be affected:');
  foreach ($affected_indexes as $index) {
    drush_print(dt(' * @name (@machine_name)', array(
      '@name' => $index->name,
      '@machine_name' => $index->machine_name,
    )));
  }

  // Give the user a chance to bail.
  if (!drush_confirm(dt("Do you really want to destroy and re-create the Elasticsearch index '!es_index'?", array('!es_index' => $es_index)))) {
    return drush_user_abort();
  }

  // Remove the existing indexes.
  foreach ($affected_indexes as $index) {
    try {
      $index->server()->removeIndex($index);
    }
    catch (Exception $e) {}
  }

  // Re-create and re-index the indexes.
  foreach ($affected_indexes as $index) {
    try {
      $index->force_create['number_of_shards'] = $number_of_shards;
      $index->force_create['number_of_replicas'] = $number_of_replicas;
      $index->server()->addIndex($index);
      $index->reindex();
      drush_log(dt('Re-initialized Search API index @name (@machine_name)', array(
        '@name' => $index->name,
        '@machine_name' => $index->machine_name,
      )), 'ok');
    }
    catch (Exception $e) {
      // This exception, on the other hand, is important.
      return drush_set_error($e->getMessage());
    }
  }
}
