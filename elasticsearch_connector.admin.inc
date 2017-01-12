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
function elasticsearch_connector_status_page() {
  $headers = array(
    array('data' => t('Cluster name')),
    array('data' => t('Status')),
    array('data' => t('Cluster Status')),
    array('data' => t('Operations')),
  );

  $rows = array();

  $clusters = elasticsearch_connector_clusters();
  foreach ($clusters as $cluster) {
    $cluster_info = elasticsearch_connector_get_cluster_info($cluster);
    $edit_link_title = ($cluster->export_type & EXPORT_IN_CODE) ? t('Override') : t('Edit');
    if ($cluster->type == 'Overridden') {
      $edit_link_title = $cluster->type;
    }

    $operations = theme('links__ctools_dropbutton', array(
      'links' => array(
        array('title' => $edit_link_title, 'href' => elasticsearch_connector_main_settings_path() . '/clusters/' . $cluster->cluster_id . '/edit'),
        array('title' => t('Info'), 'href' => elasticsearch_connector_main_settings_path() . '/clusters/' . $cluster->cluster_id . '/info'),
        array('title' => t('Indices'), 'href' => elasticsearch_connector_main_settings_path() . '/clusters/' . $cluster->cluster_id . '/indices'),
        array('title' => t('Delete'), 'href' => elasticsearch_connector_main_settings_path() . '/clusters/' . $cluster->cluster_id . '/delete'),
      ),
      'attributes' => array(
        'class' => array('links', 'inline'),
      ),
    ));

    if (!empty($cluster_info['info']) && elasticsearch_connector_check_status($cluster_info['info'])) {
      $info = $cluster_info['health']['status'];
      $version = $cluster_info['info']['version']['number'];
    }
    else {
      $info = t('Not available');
      $version = '';
    }


    $row = array();
    $row[] = $cluster->name . ($version ? ' (' . t('server version: @ver', array('@ver' => $version)) . ')' : '');
    $row[] = (!empty($cluster->status) ? t('Active') : t('Inactive'));
    $row[] = $info;
    $row[] = $operations;

    $rows[] = $row;
  }

  $output['elasticsearch_connector']['table'] = array(
    '#theme' => 'table',
    '#header' => $headers,
    '#rows' => $rows,
    '#attributes' => array('class' => array('admin-elasticsearch-connector')),
  );

  return $output;
}

/**
 * Elasticsearch Connector display all indices in cluster.
 *
 * @param object
 * @return array
 */
function elasticsearch_connector_cluster_indices($cluster) {
  $headers = array(
    array('data' => t('Index name')),
    array('data' => t('Docs')),
    array('data' => t('Size')),
    array('data' => t('Operations')),
  );

  $rows = array();
  $cluster_info = elasticsearch_connector_get_cluster_info($cluster);
  $client = $cluster_info['client'];

  if ($client && !empty($cluster_info['info']) && elasticsearch_connector_check_status($cluster_info['info'])) {
    $indices = $client->getIndicesStats();
    foreach ($indices['indices'] as $index_name => $index_info) {
      $row = array();

      $operations = theme('links__ctools_dropbutton', array(
        'links' => array(
          array('title' => t('Edit'), 'href' => elasticsearch_connector_main_settings_path() . '/clusters/' . $cluster->cluster_id . '/indices/' . $index_name . '/edit'),
          array('title' => t('Aliases'), 'href' => elasticsearch_connector_main_settings_path() . '/clusters/' . $cluster->cluster_id . '/indices/' . $index_name . '/aliases'),
          array('title' => t('Delete'), 'href' => elasticsearch_connector_main_settings_path() . '/clusters/' . $cluster->cluster_id . '/indices/' . $index_name . '/delete'),
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

  $output['elasticsearch_connector']['table'] = array(
    '#theme' => 'table',
    '#header' => $headers,
    '#rows' => $rows,
    '#attributes' => array('class' => array('admin-elasticsearch-connector-indices')),
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
function elasticsearch_connector_cluster_indices_aliases($cluster, $index_name) {
  $headers = array(
    array('data' => t('Alias name')),
  );

  $rows = array();

  $cluster_info = elasticsearch_connector_get_cluster_info($cluster);
  $client = $cluster_info['client'];

  if ($client && !empty($cluster_info['info']) && elasticsearch_connector_check_status($cluster_info['info'])) {
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

  $output['elasticsearch_connector']['table'] = array(
    '#theme' => 'table',
    '#header' => $headers,
    '#rows' => $rows,
    '#attributes' => array('class' => array('admin-elasticsearch-connector-alias')),
  );

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

  $cluster_status = elasticsearch_connector_get_cluster_info($cluster);
  $cluster_client = $cluster_status['client'];

  $node_rows = $cluster_statistics_rows = $cluster_health_rows = array();

  if (isset($cluster_client) && !empty($cluster_status['info']) && elasticsearch_connector_check_status($cluster_status['info'])) {
    $node_stats = $cluster_client->getNodesProperties();
    $total_docs = $total_size = 0;
    if (isset($node_stats['stats'])) {
      foreach ($node_stats['stats']['nodes'] as $node_key => $node_values) {
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
        array('data' => (isset($cluster_status['state']['metadata']['indices']) ? count($cluster_status['state']['metadata']['indices']) : 0) . '<br/>' . t('Indices')),
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
      'unassigned_shards'     => t('Unassigned shards'),
      'delayed_unassigned_shards' => t('Delayed unassigned shards'),
      'number_of_pending_tasks' => t('Number of pending tasks'),
      'number_of_in_flight_fetch' => t('Number of in flight fetch'),
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
function elasticsearch_connector_edit_cluster($form, $form_state, $cluster = NULL) {
  $form = array();

  // TODO: Lock the edit of status if the cluster_id is lock by a module.

  elasticsearch_connector_set_breadcrumb(array(
    l(t('Elasticsearch Clusters'), elasticsearch_connector_main_settings_path() . '/clusters'))
  );

  $form['cluster'] = array(
    '#type'  => 'value',
    '#value' => $cluster,
  );

  $form['name'] = array(
    '#type' => 'textfield',
    '#title' => t('Administrative cluster name'),
    '#default_value' => empty($cluster->name) ? '' : $cluster->name,
    '#description' => t('Enter the administrative cluster name that will be your Elasticsearch cluster unique identifier.'),
    '#required' => TRUE,
  );

  $form['cluster_id'] = array(
    '#type' => 'machine_name',
    '#title' => t('Cluster id'),
    '#default_value' => !empty($cluster->cluster_id) ? $cluster->cluster_id : '',
    '#maxlength' => 125,
    '#description' => t('A unique machine-readable name for this Elasticsearch cluster.'),
    '#machine_name' => array(
      'exists' => 'elasticsearch_connector_cluster_load',
    ),
    '#required' => TRUE,
    '#disabled' => !empty($cluster->cluster_id),
  );

  $form['url'] = array(
    '#type' => 'textfield',
    '#title' => t('Server URL'),
    '#default_value' => !empty($cluster->url) ? $cluster->url : '',
    '#description' => t(
      'URL and port of a server (node) in the cluster. ' .
      'Please, always enter the port even if it is default one. ' .
      'Nodes will be automatically discovered. ' .
      'Examples: http://localhost:9200 or https://localhost:443.'),
    '#required' => TRUE,
  );

  $form['status_info'] = elasticsearch_connector_edit_cluster_form_info($cluster);

  $default = elasticsearch_connector_get_default_connector();
  $form['default'] = array(
    '#type' => 'checkbox',
    '#title' => t('Make this cluster default connection'),
    '#description' => t('If the cluster connection is not specified the API will use the default connection.'),
    '#default_value' => (empty($default) || (!empty($cluster->cluster_id) && $cluster->cluster_id == $default)) ? '1' : '0',
  );

  $form['options'] = array(
    '#tree' => TRUE
  );

  $form['options']['multiple_nodes_connection'] = array(
    '#type' => 'checkbox',
    '#title' => t('Use multiple nodes connection'),
    '#description' => t('Automatically discover all nodes and use them in the cluster connection. ' .
      'Then the Elasticsearch client can distribute the query execution on random base between nodes.'),
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

  $form['options']['use_authentication'] = array(
    '#type' => 'checkbox',
    '#title' => t('Use authentication'),
    '#description' => t('Use HTTP authentication method to connect to Elasticsearch.'),
    '#default_value' => (!empty($cluster->options['use_authentication']) ? 1 : 0),
    '#suffix' => '<div id="hosting-iframe-container">&nbsp;</div>',
  );

  $form['options']['authentication_type'] = array(
    '#type' => 'radios',
    '#title' => t('Authentication type'),
    '#description' => t('Select the http authentication type.'),
    '#options'  => array(
      'Digest' => t('Digest'),
      'Basic'  => t('Basic'),
      'NTLM'   => t('NTLM')
    ),
    '#default_value' => (!empty($cluster->options['authentication_type']) ? $cluster->options['authentication_type'] : 'Digest'),
    '#states' => array (
      'visible' => array(
        ':input[name="options[use_authentication]"]' => array('checked' => TRUE),
      ),
    ),
  );

  $form['options']['username'] = array(
    '#type' => 'textfield',
    '#title' => t('Username'),
    '#description' => t('The username for authentication.'),
    '#default_value' => (!empty($cluster->options['username']) ? $cluster->options['username'] : ''),
    '#states' => array (
      'visible' => array(
        ':input[name="options[use_authentication]"]' => array('checked' => TRUE),
      ),
    ),
  );

  $form['options']['password'] = array(
    '#type' => 'textfield',
    '#title' => t('Password'),
    '#description' => t('The password for authentication.'),
    '#default_value' => (!empty($cluster->options['password']) ? $cluster->options['password'] : ''),
    '#states' => array (
      'visible' => array(
        ':input[name="options[use_authentication]"]' => array('checked' => TRUE),
      ),
    ),
  );

  $form['options']['timeout'] = array(
    '#type' => 'textfield',
    '#title' => t('Connection timeout'),
    '#size'  => 20,
    '#required' => TRUE,
    '#element_validate' => array('element_validate_number'),
    '#description' => t('After how many seconds the connection should timeout if there is no connection to Elasticsearch.'),
    '#default_value' => (!empty($cluster->options['timeout']) ? $cluster->options['timeout'] : ELASTICSEARCH_CONNECTOR_DEFAULT_TIMEOUT),
  );

  $form['actions'] = array(
    '#type' => 'actions',
  );

  $form['actions']['save'] = array(
    '#type' => 'submit',
    '#validate' => array('elasticsearch_connector_edit_cluster_validate'),
    '#submit' => array('elasticsearch_connector_edit_cluster_submit'),
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
function elasticsearch_connector_cluster_indices_add($form, &$form_state, $cluster, $index = NULL) {
  $form = array();

  $form['#cluster'] = $cluster;

  if (isset($index)) {
    $form['#index'] = $index;

    $client = elasticsearch_connector_load_library($cluster);
    $settings = array();
    try {
      $settings = $client->indices()->getSettings(array(
        'index' => $index
      ));

      $settings = $settings[$index]['settings'];
    }
    catch (Exception $e) {
      watchdog('elasticsearch_connector', $e->getMessage(), array(), WATCHDOG_WARNING);
    }
  }

  $form['index_name'] = array(
    '#type' => 'textfield',
    '#title' => t('Index name'),
    '#required' => TRUE,
    '#disabled' => isset($index),
    '#default_value' => isset($index) ? $index : '',
    '#description' => t('Enter the index name.')
  );

  $form['num_of_shards'] = array(
    '#type' => 'textfield',
    '#title' => t('Number of shards'),
    '#required' => TRUE,
    '#disabled' => isset($index),
    '#default_value' => isset($settings['index']['number_of_shards']) ? $settings['index']['number_of_shards'] : '',
    '#description' => t('Enter the number of shards for the index.')
  );

  $form['num_of_replica'] = array(
    '#type' => 'textfield',
    '#title' => t('Number of replica'),
    '#required' => TRUE,
    '#default_value' => isset($settings['index']['number_of_replicas']) ? $settings['index']['number_of_replicas'] : '',
    '#description' => t('Enter the number of shards replicas.')
  );

  $form['actions'] = array(
    '#type' => 'actions',
  );

  $form['actions']['save'] = array(
    '#type' => 'submit',
    '#validate' => array('elasticsearch_connector_cluster_indices_add_validate'),
    '#submit' => array('elasticsearch_connector_cluster_indices_add_submit'),
    '#value' => (isset($index) ? t('Update') : t('Save')),
  );

  return $form;
}

/**
 * Validate handle of cluster index creation form.
 * @param array $form
 * @param array $form_state
 */
function elasticsearch_connector_cluster_indices_add_validate($form, &$form_state) {
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
function elasticsearch_connector_cluster_indices_add_submit($form, &$form_state) {
  $values = $form_state['values'];
  $cluster = $form['#cluster'];
  $client = elasticsearch_connector_load_library($cluster);
  $index = isset($form['#index']) ? $form['#index'] : FALSE;
  if ($client) {
    if (empty($index)) {
      try {
        $index_params['index'] = $values['index_name'];
        $index_params['body']['settings']['number_of_shards']   = $values['num_of_shards'];
        $index_params['body']['settings']['number_of_replicas'] = $values['num_of_replica'];
        drupal_alter('elasticsearch_connector_add_index', $index_params, $cluster, $client);
        $response = $client->indices()->create($index_params);
        if (elasticsearch_connector_check_response_ack($response)) {
          drupal_set_message(t('The index %index has been successfully created.', array('%index' => $values['index_name'])));
        }
        else {
          drupal_set_message(t('Fail to create the index %index', array('%index' => $values['index_name'])), 'error');
        }

        // If the form has been opened in dialog, close the window if it was
        // setup to do so.
        if (elasticsearch_connector_in_dialog() && elasticsearch_connector_close_on_submit()) {
          elasticsearch_connector_close_on_redirect($cluster->cluster_id, $values['index_name']);
        }
      }
      catch (Exception $e) {
        drupal_set_message($e->getMessage(), 'error');
      }
    }
    else {
      try {
        $index_params['index'] = $values['index_name'];
        $index_params['body']['settings']['number_of_replicas'] = $values['num_of_replica'];
        drupal_alter('elasticsearch_connector_update_index', $index_params, $cluster, $client);
        $response = $client->indices()->putSettings($index_params);
        if (elasticsearch_connector_check_response_ack($response)) {
          drupal_set_message(t('The index %index has been successfully updated.', array('%index' => $values['index_name'])));
        }
        else {
          drupal_set_message(t('Fail to update the index %index', array('%index' => $values['index_name'])), 'error');
        }

        // If the form has been opened in dialog, close the window if it was
        // setup to do so.
        if (elasticsearch_connector_in_dialog() && elasticsearch_connector_close_on_submit()) {
          elasticsearch_connector_close_on_redirect($cluster->cluster_id, $values['index_name']);
        }
      }
      catch (Exception $e) {
        drupal_set_message($e->getMessage(), 'error');
      }
    }
  }
}

/**
 * Build the dynamic cluster status.
 *
 * @param array $cluster_info
 * @param bool $ajax
 * @return array
 */
function elasticsearch_connector_edit_cluster_form_info($cluster) {
  $element = array();

  if (isset($cluster->url)) {
    try {
      $cluster_info = elasticsearch_connector_get_cluster_info($cluster);
      if ($cluster_info) {
        $headers = array(
          array('data' => t('Cluster name')),
          array('data' => t('Status')),
          array('data' => t('Number of nodes')),
        );

        if (isset($cluster_info['state']) && !isset($cluster_info['state']['error'])) {
          $rows = array(
            array(
              $cluster_info['health']['cluster_name'],
              $cluster_info['health']['status'],
              $cluster_info['health']['number_of_nodes'],
            )
          );

          $element = array(
            '#theme' => 'table',
            '#header' => $headers,
            '#rows' => $rows,
            '#attributes' => array(
              'class' => array('admin-elasticsearch-connector'),
              'id' => 'cluster-info'
            ),
          );
        }
        else {
          $rows = array(
            array(
              t('Unknown'),
              t('Unavailable'),
              '',
            )
          );

          $element = array(
            '#theme' => 'table',
            '#header' => $headers,
            '#rows' => $rows,
            '#attributes' => array(
              'class' => array('admin-elasticsearch-connector'),
              'id' => 'cluster-info'
            ),
          );
        }
      }
      else {
        $element['#type'] = 'markup';
        $element['#markup'] = '<div id="cluster-info">&nbsp;</div>';
      }
    }
    catch (\Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
    }
  }

  return $element;
}

/**
 * Handle the cluster add/edit validations.
 * @param array $form
 * @param array $form_state
 */
function elasticsearch_connector_edit_cluster_validate($form, &$form_state) {
  $values = (object)$form_state['values'];
  $cluster_info = elasticsearch_connector_get_cluster_info($values);

  // If the new cluster inaccessible from the environment, but isn't set to
  // active, warn only.
  if ((!isset($cluster_info['info']) || !elasticsearch_connector_check_status($cluster_info['info'])) && $values->status == 0) {
    drupal_set_message(t('Cannot connect to the cluster!'),'warning');
  }
  elseif (!isset($cluster_info['info']) || !elasticsearch_connector_check_status($cluster_info['info'])) {
    form_set_error('url', t('Cannot connect to the cluster!'));
  }

  // Complain if we are removing the default.
  $default = elasticsearch_connector_get_default_connector();
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
function elasticsearch_connector_edit_cluster_submit($form, &$form_state) {
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
  $default = elasticsearch_connector_get_default_connector();
  if ($form_state['values']['default'] == 1 || empty($default)) {
    elasticsearch_connector_set_default_connector($cluster->cluster_id);
  }

  // Save the cluster.
  elasticsearch_connector_cluster_save($cluster);

  // Set a message for the user.
  if (empty($form_state['values']['cluster'])) {
    $message = t('The cluster has been created.');
  }
  else {
    $message = t('The cluster has been updated.');
  }
  drupal_set_message(filter_xss($message));

  // Redirect to the cluster listing page.
  $form_state['redirect'] = elasticsearch_connector_main_settings_path() . '/clusters';
}

/**
 * Form constructor for the index deletion confirmation form.
 *
 * @see elasticsearch_connector_cluster_indices_delete_submit()
 */
function elasticsearch_connector_cluster_indices_delete($form, &$form_state, $cluster, $index) {
  $form = array();
  $locked = _elasticsearch_connector_check_if_index_locked($cluster, $index);
  if (empty($locked)) {
    $form['cluster'] = array('#type' => 'value', '#value' => $cluster);
    $form['index'] = array('#type' => 'value', '#value' => $index);
    return confirm_form($form,
        t('Are you sure you want to delete the index %index from cluster %name?',
          array(
            '%index' => $index,
            '%name' => $cluster->name)
          ),
        elasticsearch_connector_main_settings_path() . '/clusters/' . $cluster->cluster_id . '/indices',
        t('This action cannot be undone.'),
        t('Delete'),
        t('Cancel')
    );
  }
  else {
    $form['item'] = array(
      '#type' => 'item',
      '#markup' => t('Unable the delete this index because it\'s locked by following modules:')
      . theme('item_list', array('items' => $locked)),
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
function elasticsearch_connector_cluster_indices_delete_submit($form, &$form_state) {
  $client = elasticsearch_connector_load_library($form_state['values']['cluster']);
  if ($client) {
    try {
      $client->indices()->delete(array('index' => $form_state['values']['index']));
      drupal_set_message(t('%name has been deleted.', array('%name' => $form_state['values']['index'])));
      $form_state['redirect'] = elasticsearch_connector_main_settings_path() . '/clusters/' . $form_state['values']['cluster']->cluster_id . '/indices';
    }
    catch (Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
    }
  }
}

/**
 * Form constructor for the cluster deletion confirmation form.
 *
 * @see elasticsearch_connector_delete_cluster_submit()
 */
function elasticsearch_connector_delete_cluster($form, &$form_state, $cluster) {
  $locked = _elasticsearch_connector_check_if_cluster_locked($cluster);

  if (empty($locked)) {
    $form['cluster'] = array('#type' => 'value', '#value' => $cluster);
    return confirm_form($form,
        t('Are you sure you want to delete %name?', array('%name' => $cluster->name)),
        elasticsearch_connector_main_settings_path() . '/clusters',
        t('This action cannot be undone.'),
        t('Delete'),
        t('Cancel')
    );
  }
  else {
    $form['item'] = array(
      '#type' => 'item',
      '#markup' => t('Unable the delete this cluster because it\'s locked by following modules:')
       . theme('item_list', array('items' => $locked)),
    );

    return $form;
  }
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
    elasticsearch_connector_cluster_delete($cluster);
  }

  drupal_set_message(t('%name has been deleted.', array('%name' => $cluster->name)));
  $form_state['redirect'] = elasticsearch_connector_main_settings_path() . '/clusters';
}
