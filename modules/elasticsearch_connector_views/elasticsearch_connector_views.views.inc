<?php
/**
 * @file elasticsearch_connector_views.views.inc
 * The auto-load file extension for views implementation.
 */


/**
 * Implementation of hook_views_plugins().
 */
function elasticsearch_connector_views_views_plugins() {
  // TODO: Think of building "Exposed form" (exposed_form key) plugin to handle facets with
  // exposed forms.

  return array(
    'module' => 'elasticsearch_connector_views',
    'query' => array(
      'elasticsearch_connector_views_query' => array(
        'title' => t('Elasticsearch Connector Query'),
        'help' => t('Query that allows you to search with Elasticsearch Connector.'),
        'handler' => 'elasticsearch_connector_views_query',
        'parent' => 'views_query',
      ),
    ),
  );

}

/**
 * Implementation of hook_views_data().
 */
function elasticsearch_connector_views_views_data() {
  $data = array();

  foreach (elasticsearch_connector_clusters(FALSE) as $cluster) {
    $info = elasticsearch_connector_get_cluster_info($cluster);
    $elastic_client = $info['client'];
    if ($elastic_client && !empty($info['info']) && elasticsearch_connector_check_status($info['info'])) {
      $indices = $elastic_client->indices()->stats();
      if (!empty($indices['indices'])) {
        foreach ($indices['indices'] as $index_name => $index_info) {
          // In elasticsearch the table is actually the document type.
          // So get all types and build data array.
          $mapping = $elastic_client->indices()->getMapping(array('index' => $index_name));
          if (!empty($mapping[$index_name]['mappings'])) {
            foreach ($mapping[$index_name]['mappings'] as $type_name => $type_settings) {
              $name = format_string('@cluster (@index_name:@type)', array('@cluster' => $cluster->name, '@index_name' => $index_name, '@type' => $type_name));
              $base_table = 'elsv__' . $cluster->cluster_id . '__' . $index_name . '__' . $type_name;

              $data[$base_table]['table']['group'] = t('ES');
              $data[$base_table]['table']['base'] = array(
                'query class' => 'elasticsearch_connector_views_query',
                'title' => t('Cluster !name', array('!name' => $name)),
                'help' => t('Searches the site with the Elasticsearch search engine for !name', array('!name' => $name)),
              );

              // Get the list of the fields in index directly from Solr.
              if (!empty($type_settings['properties'])) {
                $fields = _elasticsearch_connector_views_handle_fields($base_table, $data, $type_settings['properties']);
              }

              // Keyword field.
              $data[$base_table]['keyword'] = array(
                'title' => t('Search'),
                'help' => t('Fulltext search'),
                'filter' => array(
                  'handler' => 'elasticsearch_connector_views_keyword_handler_filter',
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
                  'handler' => 'elasticsearch_connector_views_handler_field',
                  'click sortable' => TRUE,
                ),
              );
            }

          }
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
 * @param array $data
 * @param array $fields
 * @param string $base_field_name
 */
function _elasticsearch_connector_views_handle_fields($base_table, &$data, $fields, $base_field_name = '') {
  if (!empty($fields)) {
    foreach ($fields as $field_name => $field) {
      // TODO: Restrict some fields if needed.
      // TODO: Handle boolean.
      if (empty($field['type']) && isset($field['properties'])) {
        $field_type = 'object';
      }
      else {
        $field_type = $field['type'];
      }
      $filter_handler = 'elasticsearch_connector_views_handler_filter';
      $field_handler = 'elasticsearch_connector_views_handler_field';
      $set = TRUE;
      switch ($field_type) {
        case 'object':
          if (!empty($field['properties'])) {
            _elasticsearch_connector_views_handle_fields($base_table, $data, $field['properties'], $base_field_name . $field_name . '.');
          }
          $set = FALSE;
          break;
        case 'date':
          $filter_handler = 'elasticsearch_connector_views_handler_filter_date';
          $field_handler = 'elasticsearch_connector_views_handler_field_date';
          break;
        case 'text':
        case 'string':
          case 'string':
          if(module_exists('views_autocomplete_filters')) {
            $filter_handler = 'elasticsearch_connector_views_handler_filter_string_autocomplete';
          }
          else {
            $filter_handler = 'elasticsearch_connector_views_handler_filter_string';
          }
          break;

          // Handle numeric filter type.
        case 'integer':
        case 'long':
        case 'float':
        case 'double':
          $filter_handler = 'elasticsearch_connector_views_handler_filter_numeric';
          break;
      }

      if ($set) {
        $data[$base_table][$base_field_name . $field_name] = array(
          'title' => $base_field_name . $field_name,
          'help' => $base_field_name . $field_name,
          'field' => array(
            'handler' => $field_handler,
            'click sortable' => TRUE,
          ),
          'filter' => array(
            'handler' => $filter_handler,
          ),
          'sort' => array(
            'handler' => 'elasticsearch_connector_views_handler_sort',
          ),
          'argument' => array(
            'handler' => 'elasticsearch_connector_views_handler_argument',
          ),
        );

        // Default sort field for label.
        $sort_field_name = ($field_name == 'label') ? 'sort_label' : '';
        // Check if corresponding sort_ field exists. We remove prefix from field
        // name (for example prefix "ss_" from "ss_name") and check if "sort_*"
        // field is available.

        // TODO: Handle sorting field.

        //             if (array_key_exists('sort_' . substr($field_name, 2), $solr_fields)) {
        //               $sort_field_name = 'sort_' . substr($field_name, 2);
        //             }
        //             if (!empty($sort_field_name)) {
        //               // Use the sort field for click sorting.
        //               $data[$base_table][$field_name]['field']['click sort field'] = $sort_field_name;
        //               // And use the sort field for explicit sorts.
        //               $data[$base_table][$field_name]['sort']['real field'] = $sort_field_name;
        //             }
      }
    }
  }
}

