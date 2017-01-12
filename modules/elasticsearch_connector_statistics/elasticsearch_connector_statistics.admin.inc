<?php

/**
 * @file
 * Admin page callbacks for the Statistics module.
 */

/**
 * Form constructor for the statistics administration form.
 *
 * @ingroup forms
 * @see system_settings_form()
 */
function elasticsearch_connector_statistics_settings_form() {

  $form['elasticsearch_connector_statistics_cluster'] = array(
    '#type' => 'ec_index',
    '#title' => t('Select cluster'),
    '#required' => TRUE,
    '#default_value' => variable_get('elasticsearch_connector_statistics_cluster', array()),
    '#description' => t('Select the settings in order to be able to hold the logs.')
  );

  // Access log settings.
  $form['access'] = array(
    '#type' => 'fieldset',
    '#title' => t('Access log settings'),
  );
  $form['access']['elasticsearch_connector_statistics_enable_access_log'] = array(
    '#type' => 'checkbox',
    '#title' => t('Enable access log'),
    '#default_value' => variable_get('elasticsearch_connector_statistics_enable_access_log', 0),
    '#description' => t('Log each page access. Required for referrer statistics.'),
  );
  $form['access']['elasticsearch_connector_statistics_enable_access_log_admin'] = array(
    '#type' => 'checkbox',
    '#title' => t('Enable access log of admin pages'),
    '#default_value' => variable_get('elasticsearch_connector_statistics_enable_access_log_admin', 0),
    '#description' => t('Log every admin page access'),
  );

  $form['access']['elasticsearch_connector_statistics_ttl'] = array(
    '#type' => 'textfield',
    '#title' => t('Elasticsearch TTL Interval'),
    '#default_value' => variable_get('elasticsearch_connector_statistics_ttl', ELASTICSEARCH_CONNECTOR_STATS_DEFAULT_INTERVAL),
    '#element_validate' => array('_elasticsearch_connector_validate_ttl_field'),
    '#description' => t('Use format like 1d. Suffix can be d (days), m (minutes), h (hours), ms (milliseconds) or w (weeks).'
        . ' You can dynamically update the default interval.'
        . ' However it won\'t change the TTL of already indexed messages but will be used for future logs.'),
  );

  $form['#validate'] = array('elasticsearch_connector_statistics_settings_form_validate');
  $form['#submit'] = array('elasticsearch_connector_statistics_settings_form_submit');

  return system_settings_form($form);
}

/**
 * Validate the setting form submission.
 *
 * @param array $form
 * @param array $form_state
 */
function elasticsearch_connector_statistics_settings_form_validate($form, &$form_state) {
  $values = &$form_state['values'];
  if (!empty($form_state['triggering_element']['#ajax']) || empty($values['elasticsearch_connector_statistics_cluster']['index'])) {
    return;
  }

  $client = elasticsearch_connector_get_client_by_id($values['elasticsearch_connector_statistics_cluster']['cluster_id']);
  $index_name = $values['elasticsearch_connector_statistics_cluster']['index'];
  if ($client) {
    if (!$client->indices()->exists(array('index' => $index_name))) {
      form_set_error('elasticsearch_connector_statistics_cluster', t('The index doesn\'t exists. Please created it before continue.'));
    }
  }
  else {
    form_set_error('elasticsearch_connector_statistics_cluster', t('Cannot connect to the cluster for some reason! Please contact your system administrator.'));
  }
}

/**
 * Handling the submission of elasticsearch_connector_statistics_settings_form form.
 * @param array
 * @param array
 */
function elasticsearch_connector_statistics_settings_form_submit($form, &$form_state) {
  $values = &$form_state['values'];
  $client = elasticsearch_connector_get_client_by_id($values['elasticsearch_connector_statistics_cluster']['cluster_id']);

  $index_name = $values['elasticsearch_connector_statistics_cluster']['index'];
  $type_name = variable_get('elasticsearch_connector_statistics_type',  ELASTICSEARCH_CONNECTOR_STATS_DEFAULT_TYPE);

  if ($client) {
    if (!$client->indices()->existsType(array('index' => $index_name, 'type' => $type_name))) {
      elasticsearch_connector_statistics_create_type($client, $index_name, $type_name, $values['elasticsearch_connector_statistics_ttl']);
    }
  }
}

/**
 * Page callback: Displays the "recent hits" page.
 *
 * This displays the pages with recent hits in a given time interval that
 * haven't been flushed yet. The flush interval is set on the statistics
 * settings form, but is dependent on cron running.
 *
 * @return
 *   A render array containing information about the most recent hits.
 */
function elasticsearch_connector_statistics_recent_hits() {
  $header = array(
    array('data' => t('Timestamp'), 'field' => array('timestamp'), 'sort' => 'desc'),
    array('data' => t('Page'), 'field' => array('path')),
    array('data' => t('User'), 'field' => array('username')),
    array('data' => t('Operations'))
  );

  $rows = array();
  $client_id = elasticsearch_connector_statistics_get_cluster_vars();
  if (!empty($client_id)) {
    $client = elasticsearch_connector_get_client_by_id($client_id);
    if ($client) {
      try {

        $params = array();
        $index_name = elasticsearch_connector_statistics_get_cluster_vars('index');
        $params['index'] = $index_name;
        $params['type']  = variable_get('elasticsearch_connector_statistics_type',  ELASTICSEARCH_CONNECTOR_STATS_DEFAULT_TYPE);

        if ($client->indices()->exists(array('index' => $index_name)) && $client->indices()->existsType($params)) {
          $sort = tablesort_get_sort($header);
          $sort_fields = tablesort_get_order($header);
          $limit = variable_get('elasticsearch_connector_statistics_page_limit', 50);
          $current_page = pager_find_page();
          $params['body']['size']  = $limit;
          $params['body']['from']  = $current_page*$limit;
          foreach ($sort_fields['sql'] as $sort_field) {
            $params['body']['sort'][$sort_field]['order'] = $sort;
          }

          // Filter the results if there are filters specified.
          $params['body']['query'] = array(
            'match_all' => array()
          );

          $results = $client->search($params);

          if (!empty($results['hits']['hits'])) {
            foreach ($results['hits']['hits'] as $log) {
              $account = user_load($log['_source']['uid']);
              $rows[] = array(
                array('data' => format_date($log['_source']['timestamp'], 'short'), 'class' => array('nowrap')),
                _elasticsearch_connector_statistics_format_item($log['_source']['title'], $log['_source']['path']),
                theme('username', array('account' => $account)),
                l(t('details'), "admin/reports/ecs-access/" . $log['_id']));
            }
          }

          if (!empty($results['hits']['total'])) {
            pager_default_initialize($results['hits']['total'], $limit);
          }
        }
      }
      catch (Exception $e) {
        // Show the error message to the user.
        drupal_set_message($e->getMessage(), 'error');
      }
    }
  }

  $build['statistics_table'] = array(
    '#theme' => 'table',
    '#header' => $header,
    '#rows' => $rows,
    '#empty' => t('No statistics available.'),
  );
  $build['statistics_pager'] = array('#theme' => 'pager');
  return $build;
}

/**
 * Page callback: Displays statistics for the "top pages" (most accesses).
 *
 * This displays the pages with the most hits (the "top pages") within a given
 * time period that haven't been flushed yet. The flush interval is set on the
 * statistics settings form, but is dependent on cron running.
 *
 * @return
 *   A render array containing information about the the top pages.
 */
function elasticsearch_connector_statistics_top_pages() {
  $header = array(
    array('data' => t('Hits')),
    array('data' => t('Page'))
  );

  $rows = $facets = array();
  $global_facet_name = 'path_facet';
  $field_faceting = 'path';
  $client_id = elasticsearch_connector_statistics_get_cluster_vars();
  if (!empty($client_id)) {
    $client = elasticsearch_connector_get_client_by_id($client_id);
    if ($client) {
      try {
        $params = array();
        $index_name = elasticsearch_connector_statistics_get_cluster_vars('index');
        $params['index'] = $index_name;
        $params['type']  = variable_get('elasticsearch_connector_statistics_type',  ELASTICSEARCH_CONNECTOR_STATS_DEFAULT_TYPE);
        $params['search_type'] = 'count';
        $params['body']['facets'][$global_facet_name]['terms']['field'] = $field_faceting;
        $params['body']['facets'][$global_facet_name]['terms']['size'] = variable_get('elasticsearch_connector_statistics_facet_size', 1000);
        $search_result = $client->search($params);
        if (!empty($search_result['facets'])) {
          foreach ($search_result['facets'][$global_facet_name]['terms'] as $facet) {
            $facet['term'] = l(url($facet['term'], array('absolute' => FALSE)), $facet['term']);
            $rows[] = array($facet['count'], $facet['term']);
          }
        }
      }
      catch (Exception $e) {
        // Show the error message to the user.
        drupal_set_message($e->getMessage(), 'error');
      }
    }
  }

  $build['statistics_top_pages_table'] = array(
    '#theme' => 'table',
    '#header' => $header,
    '#rows' => $rows,
    '#empty' => t('No statistics available.'),
  );
  return $build;
}

/**
 * Get the top IP hits.
 */
function elasticsearch_connector_statistics_top_ip() {
  $header = array(
    array('data' => t('Hits')),
    array('data' => t('Page')),
    array('data' => user_access('block IP addresses') ? t('Operations') : '', 'colspan' => 2),
  );

  $rows = $facets = array();
  $global_facet_name = 'ip_facet';
  $field_faceting = 'ip';
  $client_id = elasticsearch_connector_statistics_get_cluster_vars();
  if (!empty($client_id)) {
    $client = elasticsearch_connector_get_client_by_id($client_id);
    if ($client) {
      try {
        $params = array();
        $index_name = elasticsearch_connector_statistics_get_cluster_vars('index');
        $params['index'] = $index_name;
        $params['type']  = variable_get('elasticsearch_connector_statistics_type',  ELASTICSEARCH_CONNECTOR_STATS_DEFAULT_TYPE);
        $params['search_type'] = 'count';
        $params['body']['facets'][$global_facet_name]['terms']['field'] = $field_faceting;
        $params['body']['facets'][$global_facet_name]['terms']['size'] = variable_get('elasticsearch_connector_statistics_facet_size', 1000);
        $search_result = $client->search($params);
        if (!empty($search_result['facets'])) {
          foreach ($search_result['facets'][$global_facet_name]['terms'] as $facet) {
            $ban_link = l(t('block IP address'), "admin/config/people/ip-blocking/" . $facet['term'], array('query' => drupal_get_destination()));
            $rows[] = array($facet['count'], $facet['term'], (user_access('block IP addresses') ? $ban_link : ''));
          }
        }
      }
      catch (Exception $e) {
        // Show the error message to the user.
        drupal_set_message($e->getMessage(), 'error');
      }
    }
  }

  $build['statistics_top_ip_table'] = array(
    '#theme' => 'table',
    '#header' => $header,
    '#rows' => $rows,
    '#empty' => t('No statistics available.'),
  );
  return $build;
}

/**
 * Page callback: Displays the "top visitors" page.
 *
 * This displays the pages with the top number of visitors in a given time
 * interval that haven't been flushed yet. The flush interval is set on the
 * statistics settings form, but is dependent on cron running.
 *
 * @return
 *   A render array containing the top visitors information.
 */
function elasticsearch_connector_statistics_top_visitors() {

  $header = array(
    array('data' => t('Hits')),
    array('data' => t('Visitor')),
    // TODO: add action to block the user.
    //array('data' => user_access('block IP addresses') ? t('Operations') : '', 'colspan' => 2),
  );

  $rows = $facets = array();
  $global_facet_name = 'uid_facet';
  $field_faceting = 'uid';
  $client_id = elasticsearch_connector_statistics_get_cluster_vars();
  if (!empty($client_id)) {
    $client = elasticsearch_connector_get_client_by_id($client_id);
    if ($client) {
      try {
        $params = array();
        $index_name = elasticsearch_connector_statistics_get_cluster_vars('index');
        $params['index'] = $index_name;
        $params['type']  = variable_get('elasticsearch_connector_statistics_type',  ELASTICSEARCH_CONNECTOR_STATS_DEFAULT_TYPE);
        $params['search_type'] = 'count';
        $params['body']['facets'][$global_facet_name]['terms']['field'] = $field_faceting;
        $params['body']['facets'][$global_facet_name]['terms']['size'] = variable_get('elasticsearch_connector_statistics_facet_size', 1000);
        $search_result = $client->search($params);
        if (!empty($search_result['facets'])) {
          foreach ($search_result['facets'][$global_facet_name]['terms'] as $facet) {
            $account = user_load($facet['term']);
            $rows[] = array($facet['count'], theme('username', array('account' => $account)));
          }
        }
      }
      catch (Exception $e) {
        // Show the error message to the user.
        drupal_set_message($e->getMessage(), 'error');
      }
    }
  }

  $build['statistics_top_visitors_table'] = array(
    '#theme' => 'table',
    '#header' => $header,
    '#rows' => $rows,
    '#empty' => t('No statistics available.'),
  );

  return $build;
}

/**
 * Page callback: Displays the "top referrers" in the access logs.
 *
 * This displays the pages with the top referrers in a given time interval that
 * haven't been flushed yet. The flush interval is set on the statistics
 * settings form, but is dependent on cron running.
 *
 * @return
 *   A render array containing the top referrers information.
 */
function elasticsearch_connector_statistics_top_referrers() {
  $header = array(
    array('data' => t('Hits'), 'field' => 'hits', 'sort' => 'desc'),
    array('data' => t('Url'), 'field' => 'url')
  );
  $rows = $facets = array();
  $global_facet_name = 'referer_facet';
  $field_faceting = 'referer';
  $client_id = elasticsearch_connector_statistics_get_cluster_vars();
  if (!empty($client_id)) {
    $client = elasticsearch_connector_get_client_by_id($client_id);
    if ($client) {
      try {
        $params = array();
        $index_name = elasticsearch_connector_statistics_get_cluster_vars('index');
        $params['index'] = $index_name;
        $params['type']  = variable_get('elasticsearch_connector_statistics_type',  ELASTICSEARCH_CONNECTOR_STATS_DEFAULT_TYPE);
        $params['search_type'] = 'count';
        $params['body']['facets'][$global_facet_name]['terms']['field'] = $field_faceting;
        $params['body']['facets'][$global_facet_name]['terms']['size'] = variable_get('elasticsearch_connector_statistics_facet_size', 1000);
        $search_result = $client->search($params);
        if (!empty($search_result['facets'])) {
          foreach ($search_result['facets'][$global_facet_name]['terms'] as $facet) {
            if (!empty($facet['term'])) {
              $facet['term'] = l($facet['term'], $facet['term']);
            }
            $rows[] = array($facet['count'], $facet['term']);
          }
        }
      }
      catch (Exception $e) {
        // Show the error message to the user.
        drupal_set_message($e->getMessage(), 'error');
      }
    }
  }

  $build['statistics_top_referrers_table'] = array(
    '#theme' => 'table',
    '#header' => $header,
    '#rows' => $rows,
    '#empty' => t('No statistics available.'),
  );

  return $build;
}

/**
 * Page callback: Gathers page access statistics suitable for rendering.
 *
 * @param $aid
 *   The unique accesslog ID.
 *
 * @return
 *   A render array containing page access statistics. If information for the
 *   page was not found, drupal_not_found() is called.
 */
function elasticsearch_connector_statistics_access_log($aid) {
  $client_id = elasticsearch_connector_statistics_get_cluster_vars();
  if (!empty($client_id)) {
    $client = elasticsearch_connector_get_client_by_id($client_id);
    if ($client) {
      try {
        $params['index'] = elasticsearch_connector_statistics_get_cluster_vars('index');
        $params['type']  = variable_get('elasticsearch_connector_statistics_type',  ELASTICSEARCH_CONNECTOR_STATS_DEFAULT_TYPE);
        $params['id']    = $aid;

        $rows = array();
        if ($client->exists($params)) {
          $result = $client->get($params);
          if (!empty($result['_id'])) {
            $rows[] = array(
              array('data' => t('URL'), 'header' => TRUE),
              l(url($result['_source']['path'], array('absolute' => TRUE)), $result['_source']['path'])
            );
            // It is safe to avoid filtering $access->title through check_plain because
            // it comes from drupal_get_title().
            $rows[] = array(
              array('data' => t('Title'), 'header' => TRUE),
              $result['_source']['title']
            );
            $rows[] = array(
              array('data' => t('Referrer'), 'header' => TRUE),
              ($result['_source']['referer'] ? l($result['_source']['referer'], $result['_source']['referer']) : '')
            );
            $rows[] = array(
              array('data' => t('Date'), 'header' => TRUE),
              format_date($result['_source']['timestamp'], 'long')
            );
            $rows[] = array(
              array('data' => t('User'), 'header' => TRUE),
              $result['_source']['username']
            );
            $rows[] = array(
              array('data' => t('Hostname'), 'header' => TRUE),
              check_plain($result['_source']['ip'])
            );

            $build['statistics_table'] = array(
              '#theme' => 'table',
              '#rows' => $rows,
            );

            return $build;
          }
        }
      }
      catch (Exception $e) {
        // Show the error message to the user.
        drupal_set_message($e->getMessage(), 'error');
      }
    }
  }

  return MENU_NOT_FOUND;
}
