<?php
/**
 * @file
 * Created on Dec 23, 2013
 *
 * TODO: Handle index edit to update the replicas settings.
 *
 *
 */

/**
 * Cluster status page callback.
 *
 * @return array
 *   A Drupal render array.
 */
//Removed. Called from src/Controller/ElasticsearchController::status
function elasticsearch_status_page() {
  $headers = array(
    array('data' => t('Cluster name')),
    array('data' => t('Status')),
    array('data' => t('Cluster Status')),
    array('data' => t('Operations')),
  );

  $rows = array();

  $clusters = elasticsearch_clusters();
  foreach ($clusters as $cluster) {
    $cluster_info = elasticsearch_get_cluster_info($cluster);
    $edit_link_title = ($cluster->export_type & EXPORT_IN_CODE) ? t('Override') : t('Edit');
    if ($cluster->type == 'Overridden') {
      $edit_link_title = $cluster->type;
    }

    // TODO: Remove theme() as per D8 API
    $operations = array(
      '#type'  => 'table',
      '#links' => array(
        array('title' => $edit_link_title, 'href' => elasticsearch_main_settings_path() . '/clusters/' . $cluster->cluster_id . '/edit'),
        array('title' => t('Info'), 'href' => elasticsearch_main_settings_path() . '/clusters/' . $cluster->cluster_id . '/info'),
        array('title' => t('Indices'), 'href' => elasticsearch_main_settings_path() . '/clusters/' . $cluster->cluster_id . '/indices'),
        array('title' => t('Delete'), 'href' => elasticsearch_main_settings_path() . '/clusters/' . $cluster->cluster_id . '/delete'),
      ),
      '#attributes' => array(
        'class' => array('links', 'inline'),
      ),
    );

    if (!empty($cluster_info['info']) && elasticsearch_check_status($cluster_info['info'])) {
      $info = $cluster_info['health']['status'];
    }
    else {
      $info = t('Not available');
    }

    $row = array();
    $row[] = $cluster->name;
    $row[] = (!empty($cluster->status) ? t('Active') : t('Inactive'));
    $row[] = $info;
    $row[] = drupal_render($operations);

    $rows[] = $row;
  }

  $output['elasticsearch']['table'] = array(
    '#theme' => 'table',
    '#header' => $headers,
    '#rows' => $rows,
    '#attributes' => array('class' => array('admin-elasticsearch')),
  );

  return $output;
}

/**
 * Elasticsearch display all indices in cluster.
 *
 * @param object
 * @return array
 */
//Removed. Called from src/Entity/Index::getClusterIndices
function elasticsearch_cluster_indices($cluster) {
  $headers = array(
    array('data' => t('Index name')),
    array('data' => t('Docs')),
    array('data' => t('Size')),
    array('data' => t('Operations')),
  );

  $rows = array();
  $cluster_info = elasticsearch_get_cluster_info($cluster);
  $client = $cluster_info['client'];

  if ($client && !empty($cluster_info['info']) && elasticsearch_check_status($cluster_info['info'])) {
    $indices = $client->indices()->stats();
    foreach ($indices['indices'] as $index_name => $index_info) {
      $row = array();

      // TODO: Remove theme() as per D8 API
      $operations = theme('links', array(
        'links' => array(
          array('title' => t('Aliases'), 'href' => elasticsearch_main_settings_path() . '/clusters/' . $cluster->cluster_id . '/indices/' . $index_name . '/aliases'),
          array('title' => t('Delete'), 'href' => elasticsearch_main_settings_path() . '/clusters/' . $cluster->cluster_id . '/indices/' . $index_name . '/delete'),
        ),
        'attributes' => array(
          'class' => array('links', 'inline'),
        ),
      ));

      $row[] = $index_name;
      $row[] = $index_info['total']['docs']['count'];
      $row[] = format_size($index_info['total']['store']['size_in_bytes']);
      $row[] = $operations;

      $rows[] = $row;
    }
  }
  else {
    drupal_set_message(t('The cluster cannot be connected for some reason.'), 'error');
  }

  $output['elasticsearch']['table'] = array(
    '#theme' => 'table',
    '#header' => $headers,
    '#rows' => $rows,
    '#attributes' => array('class' => array('admin-elasticsearch-indices')),
  );

  return $output;
}

/**
 * List all aliases for an index.
 *
 * @param object $cluster
 * @param string $index_name
 * @return array
 */
//Removed. Called from src/Entity/Index::getClusterIndicesAliases
function elasticsearch_cluster_indices_aliases($cluster, $index_name) {
  $headers = array(
    array('data' => t('Alias name')),
  );

  $rows = array();

  $cluster_info = elasticsearch_get_cluster_info($cluster);
  $client = $cluster_info['client'];

  if ($client && !empty($cluster_info['info']) && elasticsearch_check_status($cluster_info['info'])) {
    try {
      $aliases = $client->indices()->getAliases(array('index' => $index_name));
      foreach ($aliases[$index_name]['aliases'] as $alias_name => $alias_info) {
        $row = array();

        // TODO: Handle alias actions.
        $row[] = $alias_name;

        $rows[] = $row;
      }
    }
    catch (Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
    }
  }
  else {
    drupal_set_message(t('The cluster cannot be connected for some reason.'), 'error');
  }

  $output['elasticsearch']['table'] = array(
    '#theme' => 'table',
    '#header' => $headers,
    '#rows' => $rows,
    '#attributes' => array('class' => array('admin-elasticsearch-alias')),
  );

  return $output;
}

/**
 *
 * @param object $cluster
 * @return array
 */
function elasticsearch_info_cluster($cluster) {
  elasticsearch_set_breadcrumb(array(
    l(t('Elasticsearch Clusters'), elasticsearch_main_settings_path() . '/clusters'))
  );

  $cluster_status = elasticsearch_get_cluster_info($cluster);
  $cluster_client = $cluster_status['client'];

  $node_rows = $cluster_statistics_rows = $cluster_health_rows = array();

  if (isset($cluster_client) && !empty($cluster_status['info']) && elasticsearch_check_status($cluster_status['info'])) {
    $node_stats = $cluster_status['stats'];
    $total_docs = $total_size = 0;
    if (isset($node_stats)) {
      foreach ($node_stats['nodes'] as $node_key => $node_values) {
        $row = array();
        $row[] = array('data' => $node_values['name']);
        $row[] = array('data' => $node_values['indices']['docs']['count']);
        $row[] = array('data' => format_size($node_values['indices']['store']['size_in_bytes']));
        $total_docs += $node_values['indices']['docs']['count'];
        $total_size += $node_values['indices']['store']['size_in_bytes'];
        $node_rows[] = $row;
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
// Removed. Called from src/Form/ClusterForm::form
function elasticsearch_edit_cluster($form, $form_state, $cluster = NULL) {
  $form = array();

  // TODO: Lock the edit of status if the cluster_id is lock by a module.

  elasticsearch_set_breadcrumb(array(
    l(t('Elasticsearch Clusters'), elasticsearch_main_settings_path() . '/clusters'))
  );

  $form['cluster'] = array(
    '#type'  => 'value',
    '#value' => $cluster,
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
      'exists' => 'elasticsearch_cluster_load',
    ),
    '#default_value' => !empty($cluster->cluster_id) ? $cluster->cluster_id : '',
    '#disabled' => !empty($cluster->cluster_id), // Cannot change it once set.
    '#description' =>
      t('Unique, machine-readable identifier for this Elasticsearch environment.'),
    '#required' => TRUE,
  );

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
      'callback' => 'elasticsearch_edit_cluster_ajax',
      'effect' => 'fade',
      'event'  => 'blur'
    ),
  );

  $cluster_info = NULL;
  $form_state_active = FALSE;
  if (isset($form_state['values'])) {
    $values = (object)$form_state['values'];
    if (!empty($values->url)) {
      $cluster_info = elasticsearch_get_cluster_info($values);
      $form_state_active = TRUE;
    }
  }
  elseif (isset($cluster->url)) {
    $cluster_info = elasticsearch_get_cluster_info($cluster);
    $form_state_active = TRUE;
  }

  $form['status_info'] = elasticsearch_edit_cluster_form_info($cluster_info, $form_state_active);

  $default = elasticsearch_get_default_connector();
  $form['default'] = array(
    '#type' => 'checkbox',
    '#title' => t('Make this cluster default connection'),
    '#description' => t('If no specific cluster connection specified the API will use the default connection.'),
    '#default_value' => (empty($default) || (!empty($cluster->cluster_id) && $cluster->cluster_id == $default)) ? '1' : '0',
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
    '#default_value' => isset($cluster->status) ? $cluster->status : elasticsearch_STATUS_ACTIVE,
    '#options' => array(
      elasticsearch_STATUS_ACTIVE   => t('Active'),
      elasticsearch_STATUS_INACTIVE => t('Inactive'),
    ),
    '#required' => TRUE,
  );

  $form['actions'] = array(
    '#type' => 'actions',
  );

  $form['actions']['save'] = array(
    '#type' => 'submit',
    '#validate' => array('elasticsearch_edit_cluster_validate'),
    '#submit' => array('elasticsearch_edit_cluster_submit'),
    '#value' => t('Save')
  );

  return $form;
}

/**
 * Create new index in the cluster with shard settings and other settings.
 *
 * @param array $cluster
 * @return array
 */
// TODO in src/Form/Index::form
function elasticsearch_cluster_indices_add($form, &$form_state, $cluster) {
  $form = array();

  $form['#cluster'] = $cluster;

  $form['index_name'] = array(
    '#type' => 'textfield',
    '#title' => t('Index name'),
    '#required' => TRUE,
    '#default_value' => '',
    '#description' => t('Enter the index name.')
  );

  $form['num_of_shards'] = array(
    '#type' => 'textfield',
    '#title' => t('Number of shards'),
    '#required' => TRUE,
    '#default_value' => '',
    '#description' => t('Enter the number of shards for the index.')
  );

  $form['num_of_replica'] = array(
    '#type' => 'textfield',
    '#title' => t('Number of replica'),
    '#default_value' => '',
    '#description' => t('Enter the number of shards replicas.')
  );

  $form['actions'] = array(
    '#type' => 'actions',
  );

  $form['actions']['save'] = array(
    '#type' => 'submit',
    '#validate' => array('elasticsearch_cluster_indices_add_validate'),
    '#submit' => array('elasticsearch_cluster_indices_add_submit'),
    '#value' => t('Save'),
  );

  return $form;
}

/**
 * Validate handle of cluster index creation form.
 * @param array $form
 * @param array $form_state
 */
// TODO in src/Form/Index::validate
function elasticsearch_cluster_indices_add_validate($form, &$form_state) {
  $values = $form_state['values'];
  if (!preg_match('/^[a-z][a-z0-9_]*$/i', $values['index_name'])) {
    form_set_error('index_name', t('Enter an index name that begins with a letter and contains only letters, numbers, and underscores.'));
  }

  if (!is_numeric($values['num_of_shards']) || $values['num_of_shards'] < 1) {
    form_set_error('num_of_shards', t('Invalid number of shards.'));
  }

  if (!is_numeric($values['num_of_replica'])) {
    form_set_error('num_of_replica', t('Invalid number of replica.'));
  }
}

/**
 * Submit the values of index create form.
 * @param array $form
 * @param array $form_state
 */
// TODO in src/Form/Index::submit
function elasticsearch_cluster_indices_add_submit($form, &$form_state) {
  $values = $form_state['values'];
  $cluster = $form['#cluster'];
  $client = elasticsearch_load_library($cluster);
  if ($client) {
    try {
      $index_params['index'] = $values['index_name'];
      $index_params['body']['settings']['number_of_shards']   = $values['num_of_shards'];
      $index_params['body']['settings']['number_of_replicas'] = $values['num_of_replica'];
      $response = $client->indices()->create($index_params);
      if (elasticsearch_check_response_ack($response)) {
        drupal_set_message(t('The index %index has been successfully created.', array('%index' => $values['index_name'])));
      }
      else {
        drupal_set_message(t('Fail to create the index %index', array('%index' => $values['index_name'])), 'error');
      }

      // If the form has been opened in dialog, close the window if it was
      // setup to do so.
      if (elasticsearch_in_dialog() && elasticsearch_close_on_submit()) {
        elasticsearch_close_on_redirect($cluster->cluster_id, $values['index_name']);
      }
    }
    catch (Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
    }
  }
}


// TODO in src/
function elasticsearch_edit_cluster_ajax($form, $form_state) {
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
// Removed. Called from src/Form/ClusterForm::clusterFormInfo
function elasticsearch_edit_cluster_form_info($cluster_info = NULL, $ajax = NULL) {
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
        'class' => array('admin-elasticsearch'),
        'id'  => 'cluster-info'),
    );
  }
  elseif (!empty($ajax)) {
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
        'class' => array('admin-elasticsearch'),
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
// Removed. Called from src/Form/ClusterForm::validate
function elasticsearch_edit_cluster_validate($form, &$form_state) {
  $values = (object)$form_state['values'];
  $cluster_info = elasticsearch_get_cluster_info($values);
  if (!isset($cluster_info['info']) || !elasticsearch_check_status($cluster_info['info'])) {
    form_set_error('url', t('Cannot connect to the cluster!'));
  }

  // Complain if we are removing the default.
  $default = elasticsearch_get_default_connector();
  if ($form_state['values']['default'] == 0 && !empty($default) && $default == $form_state['values']['cluster_id']) {
    drupal_set_message(
      t('There must be a default connection. %name is still the default connection.'
        . 'Please change the default setting on the cluster you wish to set as default.',
      array(
        '%name' => $form_state['values']['name'])
      ),
      'warning'
    );
  }
}

/**
 * Handle the cluster add/edit submissions.
 *
 * @param array $form
 * @param array $form_state
 */
// Removed. Called from src/Form/ClusterForm::save
function elasticsearch_edit_cluster_submit($form, &$form_state) {
  $cluster = $form_state['values']['cluster'];

  if (!$cluster) {
    $cluster = new stdClass();
  }

  // Save specific form values.
  $cluster->name = $form_state['values']['name'];
  $cluster->cluster_id = $form_state['values']['cluster_id'];
  $cluster->url = $form_state['values']['url'];
  $cluster->status = $form_state['values']['status'];
  $cluster->options = array();
  // Handle all options automatic if we add more in future.
  foreach ($form_state['values']['options'] as $option_name => $option_value) {
    $cluster->options[$option_name] = $option_value;
  }

  // Set default connection if selected or there is no default yet.
  $default = elasticsearch_get_default_connector();
  if ($form_state['values']['default'] == 1 || empty($default)) {
    elasticsearch_set_default_connector($cluster->cluster_id);
  }

  // Save the cluster.
  elasticsearch_cluster_save($cluster);

  // Set a message for the user.
  if (empty($form_state['values']['cluster'])) {
    $message = t('The cluster has been created.');
  }
  else {
    $message = t('The cluster has been updated.');
  }
  drupal_set_message(filter_xss($message));

  // Redirect to the cluster listing page.
  $form_state['redirect'] = elasticsearch_main_settings_path() . '/clusters';
}

/**
 * Form constructor for the index deletion confirmation form.
 *
 * @see elasticsearch_cluster_indices_delete_submit()
 */
// TODO in src/Form/DeleteIndex::delete
function elasticsearch_cluster_indices_delete($form, &$form_state, $cluster, $index) {
  $form = array();
  $locked = _elasticsearch_check_if_index_locked($cluster, $index);
  if (empty($locked)) {
    $form['cluster'] = array('#type' => 'value', '#value' => $cluster);
    $form['index'] = array('#type' => 'value', '#value' => $index);
    return confirm_form($form,
        t('Are you sure you want to delete the index %index from cluster %name?',
          array(
            '%index' => $index,
            '%name' => $cluster->name)
          ),
        elasticsearch_main_settings_path() . '/clusters/' . $cluster->cluster_id . '/indices',
        t('This action cannot be undone.'),
        t('Delete'),
        t('Cancel')
    );
  }
  else {
    $form['item'] = array(
      '#type' => 'item',
      '#markup' => t('Unable the delete this index because it\'s locked by following modules:')
       . drupal_render(array(
          '#type' => 'item_list',
          '#items' => $locked)),
    );

    return $form;
  }
}

/**
 * Delete an index.
 *
 * @param array $form
 * @param array $form_state
 */
// TODO in src/Form/DeleteIndex::save
function elasticsearch_cluster_indices_delete_submit($form, &$form_state) {
  $client = elasticsearch_load_library($form_state['values']['cluster']);
  if ($client) {
    try {
      $client->indices()->delete(array('index' => $form_state['values']['index']));
      drupal_set_message(t('%name has been deleted.', array('%name' => $form_state['values']['index'])));
      $form_state['redirect'] = elasticsearch_main_settings_path() . '/clusters/' . $form_state['values']['cluster']->cluster_id . '/indices';
    }
    catch (Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
    }
  }
}

/**
 * Form constructor for the clluster deletion confirmation form.
 *
 * @see elasticsearch_delete_cluster_submit()
 */
// TODO in src/Form/DeleteCluster::delete
function elasticsearch_delete_cluster($form, &$form_state, $cluster) {
  $locked = _elasticsearch_check_if_cluster_locked($cluster);

  if (empty($locked)) {
    $form['cluster'] = array('#type' => 'value', '#value' => $cluster);
    return confirm_form($form,
        t('Are you sure you want to delete %name?', array('%name' => $cluster->name)),
        elasticsearch_main_settings_path() . '/clusters',
        t('This action cannot be undone.'),
        t('Delete'),
        t('Cancel')
    );
  }
  else {
    $form['item'] = array(
      '#type' => 'item',
      '#markup' => t('Unable the delete this cluster because it\'s locked by following modules:')
       . drupal_render(array(
          '#type' => 'item_list',
          '#items' => $locked)),
    );

    return $form;
  }
}

/**
 * Handle the submit from elasticsearch_delete_cluster() form.
 *
 * @param array $form
 * @param array $form_state
 */
// TODO in src/Form/DeleteCluster::submit
function elasticsearch_delete_cluster_submit($form, &$form_state) {
  $cluster = $form_state['values']['cluster'];
  if (isset($cluster)) {
    elasticsearch_cluster_delete($cluster);
  }

  drupal_set_message(t('%name has been deleted.', array('%name' => $cluster->name)));
  $form_state['redirect'] = elasticsearch_main_settings_path() . '/clusters';
}
