<?php

/**
 * @file
 * Views hook implementations for the Search API module.
 */

use Drupal\Component\Render\FormattableMarkup;

/**
 * Implements hook_views_data().
 */
function elasticsearch_connector_views_views_data() {
  $data = array();
  $clientManager = \Drupal::service('elasticsearch_connector.client_manager');
  /** @var \Drupal\elasticsearch_connector\ClusterManager $cluster_manager */
  $cluster_manager = \Drupal::service('elasticsearch_connector.cluster_manager');

  foreach ($cluster_manager->loadAllClusters(FALSE) as $cluster) {
    $elasticsearchClient = $clientManager->getClientForCluster($cluster);
    if ($elasticsearchClient->isClusterOk()) {
      $indices = $elasticsearchClient->indices()->stats();
      // TODO: Handle aliases also, not only indices.
      if (!empty($indices['indices'])) {
        foreach ($indices['indices'] as $index_name => $index_info) {
          $name = new FormattableMarkup(
            '@cluster (@index_name)', array(
              '@cluster' => $cluster->name,
              '@index_name' => $index_name,
            )
          );
          $base_table = 'elsv__' . $cluster->cluster_id . '__' . $index_name;

          $data[$base_table]['table']['group'] = t('Elasticsearch');
          $data[$base_table]['table']['base'] = array(
            'index' => $index_name,
            'cluster_id' => $cluster->cluster_id,
            'title' => t('Cluster :name', array(':name' => $name)),
            'help' => t('Searches the site with the Elasticsearch search engine for !name', array('!name' => $name)),
            'query_id' => 'elasticsearch_connector_views_query',
          );

          $indexMapping = $elasticsearchClient->indices()->getMapping(array('index' => $index_name));
          // Get the list of the fields in index directly from Elasticsearch.
          if (!empty($indexMapping[$index_name]['mappings']['properties'])) {
            _elasticsearch_connector_views_handle_fields($base_table, $data, $indexMapping[$index_name]['mappings']['properties']);
          }

          // Keyword field.
          $data[$base_table]['keyword'] = array(
            'title' => t('Search'),
            'help' => t('Fulltext search'),
            'filter' => array(
              'id' => 'elasticsearch_connector_views_fulltext_filter',
            ),
          );

          // Snippet field.
          $data[$base_table]['snippet'] = array(
            'title' => t('Snippet'),
            'help' => t('Search snippet'),
            'field' => array(
              'handler' => 'elasticsearch_connector_views_snippet_handler_field',
              'click sortable' => TRUE,
            ),
          );

          // Score field.
          $data[$base_table]['score'] = array(
            'title' => t('Score'),
            'help' => t('Score'),
            'field' => array(
              'id' => 'elasticsearch_connector_views_standard',
              'click sortable' => TRUE,
            ),
          );
        }
      }
    }
  }

  return $data;
}

/**
 * Handle the fields mapping and handle nested data types.
 *
 * @param string $base_table
 *   The base table value.
 * @param array $data
 *   Data array.
 * @param array $fields
 *   Fields array.
 * @param string $base_field_name
 *   Base field name.
 */
function _elasticsearch_connector_views_handle_fields($base_table, &$data, $fields, $base_field_name = '') {
  if (!empty($fields)) {
    foreach ($fields as $field_name => $field) {
      // TODO: Restrict some fields if needed.
      // TODO: Handle boolean.
      // TODO: Handle the cases with analyzed and not analyzed.
      if (empty($field['type']) && isset($field['properties'])) {
        $field_type = 'object';
      }
      else {
        $field_type = $field['type'];
      }

      $filter_handler = 'elasticsearch_connector_views_standard';
      $field_handler = 'elasticsearch_connector_views_standard';
      $set = TRUE;
      switch ($field_type) {
        case 'object':
          if (!empty($field['properties'])) {
            _elasticsearch_connector_views_handle_fields($base_table, $data, $field['properties'], $base_field_name . $field_name . '.');
          }
          $set = FALSE;
          break;

        case 'date':
          $filter_handler = 'elasticsearch_connector_views_date';
          $field_handler = 'elasticsearch_connector_views_date';
          break;

        case 'boolean':
          $filter_handler = 'elasticsearch_connector_views_boolean';
          $field_handler = 'elasticsearch_connector_views_boolean';
          break;

        // TODO: Handle the keyword also for the text fields!
        case 'text':
        case 'string':
        case 'keyword':
          // TODO: Handle the analyser and non_analyzed fields.
          // TODO: For analysed fields we need to do fulltext search.
          if (\Drupal::moduleHandler()
                     ->moduleExists('views_autocomplete_filters')
          ) {
            // TODO: Handle autocomplete.
            //$filter_handler = 'elasticsearch_connector_views_handler_filter_string_autocomplete';
          }
          else {
            $field_handler = 'elasticsearch_connector_views_markup';
            $filter_handler = 'elasticsearch_connector_views_string';
          }
          break;

        // Handle numeric filter type.
        case 'integer':
        case 'long':
        case 'float':
        case 'double':
          $filter_handler = 'elasticsearch_connector_views_numeric';
          $field_handler = 'elasticsearch_connector_views_numeric';
          break;
      }

      if ($set) {
        $data[$base_table][$base_field_name . $field_name] = array(
          'title' => $base_field_name . $field_name,
          'help' => $base_field_name . $field_name,
          'field' => array(
            'id' => $field_handler,
            'click sortable' => TRUE,
          ),
          'filter' => array(
            'id' => $filter_handler,
          ),
          'sort' => array(
            'id' => 'standard',
          ),
          // TODO: Handle the argument class.
          'argument' => array(
            'id' => 'standard',
          ),
        );
      }
    }
  }
}
