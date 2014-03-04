<?php

/**
 * Building the settings form
 * @return array
 */
function elasticsearch_connector_sequence_settings() {
  $form = array();

  $form['elasticsearch_connector_sequence_cluster'] = array(
    '#type' => 'ec_index',
    '#title' => t('Select cluster'),
    '#required' => TRUE,
    '#default_value' => variable_get('elasticsearch_connector_sequence_cluster', array()),
    '#description' => t('Select the settings in order to be able to hold the logs.')
  );

  $form['#submit'] = array('elasticsearch_connector_sequence_submit');

  return system_settings_form($form);
}

/**
 * Handle the submit of settings form. It is used for additional actions if
 * necessary.
 * @param unknown $form
 * @param unknown $form_state
 */
function elasticsearch_connector_sequence_submit($form, &$form_state) {
  $values = $form_state['values'];
  if (!empty($form_state['triggering_element']['#ajax']) || empty($values['elasticsearch_connector_sequence_cluster']['index'])) {
    return;
  }

  $index_name = $values['elasticsearch_connector_sequence_cluster']['index'];
  $type_name = variable_get('elasticsearch_connector_sequence_type', ELASTICSEARCH_CONNECTOR_SEQUENCE_DEFAULT_TYPE);
  $client = elasticsearch_connector_get_client_by_id($values['elasticsearch_connector_sequence_cluster']['cluster_id']);
  if ($client) {
    if (!$client->indices()->existsType(array('index' => $index_name, 'type' => $type_name))) {
      $mapping_params['index'] = $index_name;
      $mapping_params['type'] = $type_name;
      $my_type_mapping = elasticsearch_connector_sequence_get_mapping();
      $mapping_params['body'][$type_name] = $my_type_mapping;
      $client->indices()->putMapping(
          $mapping_params
      );
    }
  }
}

/**
 * Default index mapping for the elasticsearch watchdog index.
 * @param  integer $ttl
 *   Time To Live setting.
 *
 * @return array
 *   The mapping index array.
 */
function elasticsearch_connector_sequence_get_mapping() {
  // Index Mapping
  $my_type_mapping = array(
    '_source' => array(
      'enabled' => TRUE
    ),
    '_all' => array(
      'enabled' => FALSE
    ),
  );

  return $my_type_mapping;
}
