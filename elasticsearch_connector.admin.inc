<?php
/**
 * @file
 * Created on Dec 23, 2013
 */

/**
 * Building the status page.
 */
function elasticsearch_connector_status_page() {
  $headers = array(
    array('data' => t('Cluster name'), 'field' => 'ecc.name'),
    array('data' => t('Status'), 'field' => 'ecc.status'),
    array('data' => t('Cluster Status')),
    array('data' => t('Operations')),
  );

  $rows = array();

  $query = db_select('elasticsearch_connector_cluster', 'ecc')
            ->extend('PagerDefault')
            ->extend('TableSort');

  $cluster_ids = $query
  ->fields('ecc',array('cluster_id'))
  ->limit(25)
  ->orderByHeader($headers)
  ->addTag('elasticsearch_connector_cluster')
  ->execute()
  ->fetchCol();

  foreach ($cluster_ids as $cluster_id) {
    $row = array();
    $cluster = elasticsearch_connector_cluster_load($cluster_id);
    $cluster_info = elasticsearch_connector_get_cluster_info($cluster->url);

    $row[] = $cluster->name;
    $row[] = (!empty($cluster->status) ? t('Active') : t('Inactive'));
    $row[] = $cluster_info['health']['status'];
    $row[] = l(t('edit'), elasticsearch_connector_main_settings_path() . '/clusters/' . $cluster->cluster_id .  '/edit')
      . ' | ' . l(t('info'), elasticsearch_connector_main_settings_path() . '/clusters/' . $cluster->cluster_id .  '/info')
      . ' | ' . l(t('delete'), elasticsearch_connector_main_settings_path() . '/clusters/' . $cluster->cluster_id .  '/delete');

    $rows[] = $row;
  }

  $output['elasticsearch_connector']['table'] = array(
    '#theme' => 'table',
    '#header' => $headers,
    '#rows' => $rows,
    '#attributes' => array('class' => array('admin-elasticsearch-connector')),
  );

  $output['pager'] = array('#markup' => theme('pager'));

  return $output;
}

/**
 *
 * @param object $cluster
 * @return array
 */
function elasticsearch_connector_info_cluster($cluster) {
  elasticsearch_connector_set_breadcrumb(array(
    l(t('Elasticsearch Clusters'), elasticsearch_connector_main_settings_path() . '/clusters'))
  );

  $cluster_status = elasticsearch_connector_get_cluster_info($cluster->url);
  $cluster_client = $cluster_status['client'];

  if (isset($cluster_client)) {
    // TODO: Think if it is not better to move the stats calling in wrapper function or
    //       making a new class interface to get the stats!

    $node_stats = $cluster_client->cluster()->nodeStats();
    $total_docs = $total_size = 0;
    $node_rows = array();
    if (isset($node_stats)) {
      foreach($node_stats['nodes'] as $node_key => $node_values ) {
        $row = array();
        $row[] = array('data' => $node_values['name']);
        $row[] = array('data' => $node_values['indices']['docs']['count']);
        $row[] = array('data' => $node_values['indices']['store']['size']);
        $total_docs += $node_values['indices']['docs']['count'];
        $total_size += $node_values['indices']['store']['size_in_bytes'];
        $node_rows[] = $row;
      }
    }
  }

  $cluster_statistics_rows = array(
    array(
      array('data' => $cluster_status['health']['number_of_nodes'] . '<br/>' . t('Nodes')),
      array('data' => $cluster_status['health']['active_shards'] + $cluster_status['health']['unassigned_shards']
            . '<br/>' . t('Total Shards')),
      array('data' => $cluster_status['health']['active_shards'] . '<br/>' . t('Successful Shards')),
      array('data' => count($cluster_status['state']['metadata']['indices']) . '<br/>' . t('Indices')),
      array('data' => $total_docs . '<br/>' . t('Total Documents')),
      array('data' => format_size($total_size) . '<br/>' . t('Total Size')),
    )
  );

  $cluster_health_rows = array();
  // TODO: Think of moving this mapping to a separate class.
  $cluster_health_mapping = array(
    'cluster_name'  => t('Cluster name'),
    'status'        => t('Status'),
    'timed_out'     => t('Time out'),
    'number_of_nodes' => t('Number of nodes'),
    'number_of_data_nodes'  => t('Number of data nodes'),
    'active_primary_shards' => t('Active primary shards'),
    'active_shards'         => t('Active shards'),
    'relocating_shards'     => t('Relocating shards'),
    'initializing_shards'   => t('Initializing shards'),
    'unassigned_shards'     => t('Unassigned shards')
  );

  foreach ($cluster_status['health'] as $health_key => $health_value) {
    $row = array();
    $row[] = array('data' => $cluster_health_mapping[$health_key]);
    $row[] = array('data' => ($health_value === FALSE ? 'False' : $health_value));
    $cluster_health_rows[] = $row;
  }

  $output['cluster_statistics_wrapper'] = array(
    '#type' => 'fieldset',
    '#title'  => t('Cluster statistics'),
    '#collapsible' => TRUE,
    '#collapsed' => FALSE
  );

  $output['cluster_statistics_wrapper']['nodes'] = array(
    '#theme' => 'table',
    '#header' => array(
      array('data' => t('Node name')),
      array('data' => t('Documents')),
      array('data' => t('Size')),
    ),
    '#rows' => $node_rows,
  );

  $output['cluster_statistics_wrapper']['cluster_statistics'] = array(
    '#theme' => 'table',
    '#header' => array(
      array('data' => t('Total'), 'colspan' => 6),
    ),
    '#rows' => $cluster_statistics_rows,
    '#attributes' => array('class' => array('admin-elasticsearch-statistics')),
  );

  $output['cluster_health'] = array(
    '#theme' => 'table',
    '#header' => array(
      array('data' => t('Cluster Health'), 'colspan' => 2),
    ),
    '#rows' => $cluster_health_rows,
    '#attributes' => array('class' => array('admin-elasticsearch-health')),
  );

  return $output;
}

/**
 * Add/edit Elasticsearch clusters.
 *
 * @param object $cluster
 * @return array $form
 */
function elasticsearch_connector_edit_cluster($form, $form_state, $cluster = NULL) {
  $form = array();

  elasticsearch_connector_set_breadcrumb(array(
    l(t('Elasticsearch Clusters'), elasticsearch_connector_main_settings_path() . '/clusters'))
  );

  $form['name'] = array(
    '#type' => 'textfield',
    '#title' => t('Cluster name'),
    '#default_value' => empty($cluster->name) ? '' : $cluster->name,
    '#description' => t('Example: ElasticaCluster'),
    '#required' => TRUE,
  );

  $form['cluster_id'] = array(
    '#type' => 'machine_name',
    '#title' => t('Cluster id'),
    '#machine_name' => array(
      'exists' => 'elasticsearch_connector_cluster_load',
    ),
    '#default_value' => !empty($cluster->cluster_id) ? $cluster->cluster_id : '',
    '#disabled' => !empty($cluster->cluster_id), // Cannot change it once set.
    '#description' =>
      t('Unique, machine-readable identifier for this Elasticsearch environment.'),
    '#required' => TRUE,
  );

  if (isset($cluster)) {
    $form['cluster'] = array(
      '#type'  => 'value',
      '#value' => $cluster
    );
  }

  $form['url'] = array(
    '#type' => 'textfield',
    '#title' => t('Server URL'),
    '#default_value' => !empty($cluster->url) ? $cluster->url : '',
    '#description' => t('Enter the URL of a node in the cluster. ' .
        'All nodes will be automatically discover. ' .
        'Example: http://localhost:9200'),
    '#required' => TRUE,
    '#ajax' => array(
      'method' => 'replace',
      'callback' => 'elasticsearch_connector_edit_cluster_ajax',
      'effect' => 'fade',
      'event'  => 'blur'
    ),
  );

  $cluster_info = NULL;
  $form_state_active = FALSE;
  if (isset($form_state['values'])) {
    $cluster_info = elasticsearch_connector_get_cluster_info($form_state['values']['url']);
    $form_state_active = TRUE;
  }
  else if (isset($cluster->url)) {
    $cluster_info = elasticsearch_connector_get_cluster_info($cluster->url);
    $form_state_active = TRUE;
  }

  $form['status_info'] = elasticsearch_connector_edit_cluster_form_info($cluster_info, $form_state_active);

  $form['default'] = array(
    '#type' => 'checkbox',
    '#title' => t('Make this cluster default connection'),
    '#description' => t('If no specific cluster connection specified the API will use the default connection.'),
    '#default_value' => (!empty($cluster->cluster_id) &&
        $cluster->cluster_id == elasticsearch_connector_get_default_connector() ? '1' : '0'),
  );

  $form['options'] = array(
    '#tree' => TRUE
  );

  $form['options']['multiple_nodes_connection'] = array(
    '#type' => 'checkbox',
    '#title' => t('Use multiple nodes connection'),
    '#description' => t('It will automatically discover all nodes and use them in the connection to the cluster. ' .
      'The Elasticsearch client can then randomise the query execution between nodes.'),
    '#default_value' => (!empty($cluster->options['multiple_nodes_connection']) ? 1 : 0),
  );

  $form['status'] = array(
    '#type' => 'radios',
    '#title' => t('Status'),
    '#default_value' => isset($cluster->status) ? $cluster->status : ELASTICSEARCH_CONNECTOR_STATUS_ACTIVE,
    '#options' => array(
      ELASTICSEARCH_CONNECTOR_STATUS_ACTIVE   => t('Active'),
      ELASTICSEARCH_CONNECTOR_STATUS_INACTIVE => t('Inactive'),
    ),
    '#required' => TRUE,
  );

  $form['actions'] = array(
    '#type' => 'actions',
  );

  $form['actions']['save'] = array(
    '#type' => 'submit',
    '#validate' => array('elasticsearch_connector_edit_cluster_validate'),
    '#submit' => array('elasticsearch_connector_edit_cluster_submit'),
    '#value' => t('Save'),
  );

  return $form;
}

function elasticsearch_connector_edit_cluster_ajax($form, $form_state) {
  return array(
    '#type' => 'ajax',
    '#commands' => array(
      ajax_command_replace("#cluster-info",  render($form['status_info'])),
    )
  );
}

/**
 * Build the dynamic cluster status.
 *
 * @param array $cluster_info
 * @param bool $ajax
 * @return array
 */
function elasticsearch_connector_edit_cluster_form_info($cluster_info = NULL, $ajax = NULL) {
  $headers = array(
    array('data' => t('Cluster name')),
    array('data' => t('Status')),
    array('data' => t('Number of nodes')),
  );

  $rows = $element = array();

  if (isset($cluster_info['state'])) {
    $rows = array(array(
      $cluster_info['health']['cluster_name'],
      $cluster_info['health']['status'],
      $cluster_info['health']['number_of_nodes'],
    ));

    $element = array(
      '#theme' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#attributes' => array(
        'class' => array('admin-elasticsearch-connector'),
        'id'  => 'cluster-info'),
    );
  }
  else if (!empty($ajax)) {
    $rows = array(array(
      t('Unknown'),
      t('Unavailable'),
      '',
    ));

    $element = array(
      '#theme' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#attributes' => array(
        'class' => array('admin-elasticsearch-connector'),
        'id'  => 'cluster-info'),
    );
  }
  else {
    $element['#type'] = 'markup';
    $element['#markup'] = '<div id="cluster-info">&nbsp;</div>';
  }

  return $element;
}

/**
 * Handle the cluster add/edit validations.
 * @param array $form
 * @param array $form_state
 */
function elasticsearch_connector_edit_cluster_validate($form, &$form_state) {
  $cluster_info = elasticsearch_connector_get_cluster_info($form_state['values']['url']);
  if (!isset($cluster_info['info']) || !$cluster_info['info'] == ELASTICSEARCH_CONNECTOR_CLUSTER_STATUS_OK) {
    form_set_error('url', t('Cannot connect to the cluster!'));
  }
}

/**
 * Handle the cluster add/edit submissions.
 *
 * @param array $form
 * @param array $form_state
 */
function elasticsearch_connector_edit_cluster_submit($form, &$form_state) {
  $values = (object)$form_state['values'];
  $values->options = serialize($values->options);
  $cluster = isset($values->cluster) ? $values->cluster : NULL;

  // TODO: Set default connection if we are saving the first cluster in settings.
  if ($values->default == 1) {
    elasticsearch_connector_set_default_connector($values->cluster_id);
  }

  if (isset($cluster)) {
    drupal_write_record('elasticsearch_connector_cluster', $values,
      array('cluster_id'));
    $message = t('The cluster has been updated.');
  }
  else {
    drupal_write_record('elasticsearch_connector_cluster', $values);
    $message = t('The cluster has been insert.');
  }

  drupal_set_message($message);
  $form_state['redirect'] = elasticsearch_connector_main_settings_path() . '/clusters';
}

/**
 * Form constructor for the node deletion confirmation form.
 *
 * @see elasticsearch_connector_delete_cluster_submit()
 */
function elasticsearch_connector_delete_cluster($form, &$form_state, $cluster) {
  $form['cluster'] = array('#type' => 'value', '#value' => $cluster);
  return confirm_form($form,
      t('Are you sure you want to delete %name?', array('%name' => $cluster->name)),
      elasticsearch_connector_main_settings_path() . '/clusters',
      t('This action cannot be undone.'),
      t('Delete'),
      t('Cancel')
  );
}

/**
 * Handle the submit from elasticsearch_connector_delete_cluster() form.
 *
 * @param array $form
 * @param array $form_state
 */
function elasticsearch_connector_delete_cluster_submit($form, &$form_state) {
  $cluster = $form_state['values']['cluster'];
  if (isset($cluster)) {
    db_delete('elasticsearch_connector_cluster')
      ->condition('cluster_id', $cluster->cluster_id, '=')
      ->execute();
  }

  drupal_set_message(t('%name has been deleted.', array('%name' => $cluster->name)));
  $form_state['redirect'] = elasticsearch_connector_main_settings_path() . '/clusters';
}