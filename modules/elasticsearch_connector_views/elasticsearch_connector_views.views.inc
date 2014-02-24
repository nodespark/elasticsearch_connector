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
  // TODO: Rework.
  foreach (elasticsearch_connector_clusters(FALSE) as $cluster) {
    $info = elasticsearch_connector_get_cluster_info($cluster);
    if (!empty($info['client'])) {
      $elastic_client = $info['client'];
      $indices = (array)$info['state']['metadata']['indices'];
      foreach ($indices as $index_name => $index_info) {
        // In elasticsearch the table is actually the document type.
        // So get all types and build data array.
        $mapping = $elastic_client->indices()->getMapping(array('index' => $index_name));
        foreach ($mapping[$index_name]['mappings'] as $type_name => $type_settings) {
          $name = format_string('@cluster (@index_name:@type)', array('@cluster' => $cluster->name, '@index_name' => $index_name, '@type' => $type_name));
          $base_table = 'elsv__' . $cluster->cluster_id . '__' . $index_name . '__' . $type_name;

          $data[$base_table]['table']['group'] = t('Elasticsearch Connector');
          $data[$base_table]['table']['base'] = array(
            'query class' => 'elasticsearch_connector_views_query',
            'title' => t('Cluster !name', array('!name' => $name)),
            'help' => t('Searches the site with the Elasticsearch search engine for !name', array('!name' => $name)),
          );

          // Get the list of the fields in index directly from Solr.
          $fields = $type_settings['properties'];

          foreach ($fields as $field_name => $field) {
            // TODO: Restrict some fields if needed.
            $field_type = $field['type'];
            $filter_handler = 'elasticsearch_connector_views_handler_filter';
            // TODO: Handle other types.
            switch ($field_type) {
              case 'date':
                $filter_handler = 'elasticsearch_connector_views_handler_filter_date';
                break;
              case 'text':
              case 'string':
                $filter_handler = 'elasticsearch_connector_views_handler_filter_string';
                break;

                case 'integer':
                 $filter_handler = 'elasticsearch_connector_views_handler_filter_numeric';
                 break;
            }

            $data[$base_table][$field_name] = array(
              'title' => $field_name,
              'help' => $field_name,
              'field' => array(
                'handler' => 'elasticsearch_connector_views_handler_field',
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

  return $data;
}