<?php
/**
 * @file
 * Created on Jan 06, 2014
 *
 * TODO: Elasticsearch doesn't support pager on facets!
 *       So limiting the results to 100. We need to research the elastic aggregation
 *       http://www.elasticsearch.org/guide/en/elasticsearch/reference/master/search-aggregations.html
 *
 */

/**
 * Building a settings form for the Elasticsearch watchdog functionality.
 * @return array Settings form
 */
function elasticsearch_watchdog_settings() {
  $form = array();

  $form['#old_cluster_id'] = variable_get('elasticsearch_watchdog_cluster_id', array());

  $form['elasticsearch_watchdog_cluster_id'] = array(
    '#type' => 'ec_index',
    '#title' => t('Select cluster'),
    '#required' => TRUE,
    '#default_value' => variable_get('elasticsearch_watchdog_cluster_id', array()),
    '#description' => t('Select the settings in order to be able to hold the logs.')
  );

  $form['elasticsearch_watchdog_type'] = array(
    '#type' => 'textfield',
    '#title' => t('Type name'),
    '#required' => TRUE,
    '#element_validate' => array('elasticsearch_connector_validate_type_field'),
    '#default_value' => variable_get('elasticsearch_watchdog_type', ELASTICSEARCH_WATCHDOG_DEFAULT_TYPE),
    '#description' => t('Enter the elasticsearch type name you want to store the logs')
  );

  $form['elasticsearch_watchdog_types_view'] = array(
    '#type' => 'textfield',
    '#title' => t('Types for view page'),
    '#required' => TRUE,
    '#default_value' => variable_get('elasticsearch_watchdog_types_view', 'watchdog_message'),
    '#description' => t('Comma separated list of watchdog types you want to monitor.')
  );

  $form['#validate'] = array('elasticsearch_watchdog_settings_validate');

  return system_settings_form($form);
}

/**
 * Validate the setting form submission.
 *
 * @param array $form
 * @param array $form_state
 */
function elasticsearch_watchdog_settings_validate($form, &$form_state) {
  $values = $form_state['values'];
  if (!empty($form_state['triggering_element']['#ajax']) || empty($values['elasticsearch_watchdog_cluster_id']['index'])) {
    return;
  }

  $client = elasticsearch_connector_get_client_by_id($values['elasticsearch_watchdog_cluster_id']['cluster_id']);

  $index_name = elasticsearch_watchdog_get_realindex_name($values['elasticsearch_watchdog_cluster_id']['index']);
  $alias_name = elasticsearch_watchdog_get_index_name($values['elasticsearch_watchdog_cluster_id']['index']);
  $old_type_name = variable_get('elasticsearch_watchdog_type', NULL);
  $type_name = elasticsearch_watchdog_get_type_name($values['elasticsearch_watchdog_type']);

  if ($client) {
    if (!$client->indices()->exists(array('index' => $index_name))) {
      form_set_error('elasticsearch_watchdog_cluster_id', t('The index doesn\'t exists. Please created it before continue.'));
    }
    else {
      if (!$client->indices()->existsType(array('index' => $index_name, 'type' => $type_name))) {
        elasticsearch_watchdog_create_type($client, $index_name, $type_name);
      }

      // Alias
      if (!$client->indices()->existsAlias(array('index' => $index_name, 'name' => $alias_name))) {
        try {
          elasticsearch_watchdog_create_alias($client, $index_name, $alias_name);
        }
        catch (Exception $e) {
          error_log($e->getMessage());
        }
      }

      if (isset($old_type_name) && $old_type_name != $type_name) {
        try {
          $client->indices()->deleteMapping(array(
            'index' => $index_name,
            'type'  => $old_type_name
          ));
          drupal_set_message(t('The old type @type has been successfully deleted.', array('@type' => $old_type_name)));
        }
        catch (Exception $e) {
          error_log($e->getMessage());
        }
      }
    }
  }
  else {
    form_set_error('elasticsearch_watchdog_cluster_id', t('Cannot connect to the cluster for some reason! Please contact your system administrator.'));
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
function elasticsearch_watchdog_get_mapping($ttl = NULL) {
  if (!isset($ttl)) {
    $ttl = variable_get('elasticsearch_watchdog_ttl', ELASTICSEARCH_WATCHDOG_DEFAULT_INTERVAL);
  }

  // Index Mapping
  $my_type_mapping = array(
    '_source' => array(
      'enabled' => TRUE
    ),
    '_all' => array(
      'enabled' => FALSE
    ),
    '_ttl' => array(
      'enabled' => TRUE,
      'default' => $ttl,
    ),
    'properties' => array(
      'uid' => array(
        'type' => 'integer',
      ),
      'username' => array(
        'type' => 'string',
        'index' => 'not_analyzed'
      ),
      'type' => array(
        'type' => 'string',
        'index' => 'not_analyzed'
      ),
      'message' => array(
        'type' => 'string',
        'index' => 'not_analyzed'
      ),
      'full_massage' => array(
        'type' => 'string',
      ),
      'variables' => array(
        'type' => 'string',
        'index' => 'not_analyzed'
      ),
      'severity' => array(
        'type' => 'string',
        'index' => 'not_analyzed'
      ),
      'link' => array(
        'type' => 'string',
        'index' => 'not_analyzed'
      ),
      'location' => array(
        'type' => 'string',
        'index' => 'not_analyzed'
      ),
      'referer' => array(
        'type' => 'string',
        'index' => 'not_analyzed'
      ),
      'hostname' => array(
        'type' => 'string',
        'index' => 'not_analyzed'
      ),
      'domain' => array(
        'type' => 'string',
        'index' => 'not_analyzed',
      ),
      'timestamp' => array(
        'type' => 'long',
      ),
      'microtime' => array(
        'type' => 'float',
      ),
      'date' => array(
        'type' => 'date',
      ),
    )
  );

  return $my_type_mapping;
}

/**
 * Creates alias to the provided index.
 *
 * @param  stdClass $client
 *   The ElasticSearch client object.
 * @param  string $index_name
 *   Name of the index to which the alias will be created.
 * @param  string $alias_name
 *   Name of the alias that will be created to the provided index.
 */
function elasticsearch_watchdog_create_alias($client, $index_name, $alias_name) {
  try {
    $client->indices()->putAlias(array(
      'name' => $alias_name,
      'index' => $index_name,
    ));
  }
  catch (Exception $e) {
    throw $e;
  }
}

/**
 * Page callback: Displays a listing of log messages.
 *
 * Messages are truncated at 56 chars. Full-length messages can be viewed on the
 * message details page.
 *
 * @see elasticsearch_watchdog_clear_log_form()
 * @see elasticsearch_watchdog_event()
 * @see elasticsearch_watchdog_filter_form()
 * @see elasticsearch_watchdog_menu()
 *
 * @ingroup logging_severity_levels
 */
function elasticsearch_watchdog_overview() {
  $filter = elasticsearch_watchdog_build_filter_query();

  $results = $rows = array();
  $classes = array(
    WATCHDOG_DEBUG     => 'elasticlog-debug',
    WATCHDOG_INFO      => 'elasticlog-info',
    WATCHDOG_NOTICE    => 'elasticlog-notice',
    WATCHDOG_WARNING   => 'elasticlog-warning',
    WATCHDOG_ERROR     => 'elasticlog-error',
    WATCHDOG_CRITICAL  => 'elasticlog-critical',
    WATCHDOG_ALERT     => 'elasticlog-alert',
    WATCHDOG_EMERGENCY => 'elasticlog-emerg',
  );

  $build['elasticsearch_watchdog_filter_form'] = drupal_get_form('elasticsearch_watchdog_filter_form');
  $build['elasticsearch_watchdog_clear_log_form'] = drupal_get_form('elasticsearch_watchdog_clear_log_form');

  $header = array(
    '', // Icon column.
    array('data' => t('Type'), 'field' => array('type')),
    array('data' => t('Date'), 'field' => array('date', 'microtime'), 'sort' => 'desc'),
    t('Message'),
    array('data' => t('User'), 'field' => array('username')),
    array('data' => t('Operations')),
  );

  $client_id = elasticsearch_watchdog_get_cluster_id();
  if (!empty($client_id)) {
    $client = elasticsearch_connector_get_client_by_id($client_id);
    if ($client) {
      try {
        $params = array();
        $index_name = elasticsearch_watchdog_get_index_name();
        $params['index'] = $index_name;
        $params['type']  = elasticsearch_watchdog_get_type_name_for_view();

        if ($client->indices()->exists(array('index' => $index_name))) {
          $sort = tablesort_get_sort($header);
          $sort_fields = tablesort_get_order($header);
          $limit = variable_get('elasticsearch_watchdog_page_limit', 50);
          $current_page = pager_find_page();
          $params['body']['size']  = $limit;
          $params['body']['from']  = $current_page*$limit;

          foreach ($sort_fields['sql'] as $sort_field) {
            $params['body']['sort'][$sort_field]['order'] = $sort;
          }

          // Filter the results if there are filters specified.
          if (!empty($filter)) {
            $params['body']['query']['filtered'] = $filter;
          }

          $results = $client->search($params);

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

  $hits = FALSE;
  if (!empty($results['hits']['hits'])) {
    $hits = TRUE;
    foreach ((array)$results['hits']['hits'] as $doc) {
      $account = user_load($doc['_source']['uid']);
      $rows[] = array('data' =>
        array(
          // Cells
          array('class' => 'icon'),
          t($doc['_source']['type']),
          format_date($doc['_source']['timestamp'], 'short'),
          theme('elasticsearch_watchdog_message', array('event_id' => $doc['_id'], 'event' => $doc['_source'], 'link' => TRUE)),
          theme('username', array('account' => $account)),
          filter_xss($doc['_source']['link']),
        ),
        // Attributes for tr
        'class' => array(drupal_html_class('elasticlog-' . $doc['_source']['type']), $classes[$doc['_source']['severity']]),
      );
    }
  }

  if ($hits) {
    $elasticsearch_connector_path = elasticsearch_connector_main_settings_path();
    $cluster = elasticsearch_connector_cluster_load($client_id);

    $build['info'] = array(
      '#type' => 'fieldset',
      '#title' => t('Info'),
    );

    $build['info']['total_messages'] = array(
      '#theme' => 'table',
      '#rows' => array(
        array(
          array('data' => t('Messages: @messages', array('@messages' => $results['hits']['total'])), 'header' => TRUE),
        ),
        array(
          array('data' => t('Cluster status: <a href="@clusters">!cluster</a>',
              array(
                '@clusters' => url($elasticsearch_connector_path . '/clusters/' . $client_id . '/info', array()),
                '!cluster' => $cluster->name
              )),
            'header' => TRUE
          )
        )
      ),
      '#attributes' => array('class' => array('elasticlog-event')),
    );
  }

  $build['elasticsearch_watchdog_table'] = array(
    '#theme' => 'table',
    '#header' => $header,
    '#rows' => $rows,
    '#attributes' => array('id' => 'admin-elasticlog'),
    '#empty' => t('No log messages available.'),
  );
  $build['elasticsearch_watchdog_pager'] = array('#theme' => 'pager');

  return $build;
}

/**
 * Page callback: Shows the most frequent log messages of a given event type.
 *
 * Messages are not truncated on this page because events detailed herein do not
 * have links to a detailed view.
 *
 * @param string $type
 *   Type of log events to display (e.g., 'search').
 *
 * @return array
 *   A build array in the format expected by drupal_render().
 *
 * @see elasticsearch_watchdog_menu()
 */
function elasticsearch_watchdog_top($type) {
  $header = array(
    array('data' => t('Count'), 'field' => 'count', 'sort' => 'desc'),
    array('data' => t('Path'), 'field' => 'message')
  );

  $rows = array();

  $global_facet_name = 'facetname_message';
  $field_faceting = 'message';
  $client_id = elasticsearch_watchdog_get_cluster_id();
  if (!empty($client_id)) {
    $client = elasticsearch_connector_get_client_by_id($client_id);
    if ($client) {
      try {
        $params = array();
        $params['index'] = elasticsearch_watchdog_get_index_name();
        $params['type']  = elasticsearch_watchdog_get_type_name_for_view();
        $params['search_type'] = 'count';
        // TODO: Change this to be filter because this is a term query!
        $params['body']['query']['term']['type'] = $type;
        $params['body']['facets'][$global_facet_name]['terms']['field'] = $field_faceting;
        $params['body']['facets'][$global_facet_name]['terms']['size'] = variable_get('elasticsearch_watchdog_facet_size', 100);
        $search_result = $client->search($params);
        if (!empty($search_result['facets'])) {
          foreach ($search_result['facets'][$global_facet_name]['terms'] as $facet) {
            $rows[] = array($facet['count'], $facet['term']);
          }
        }
      }
      catch (Exception $e) {
        drupal_set_message($e->getMessage(), 'error');
      }
    }
  }

  $build['elasticsearch_watchdog_top_table']  = array(
    '#theme' => 'table',
    '#header' => $header,
    '#rows' => $rows,
    '#empty' => t('No log messages available.'),
  );

  return $build;
}

/**
 * Page callback: Displays details about a specific log message.
 *
 * @param int $id
 *   Unique ID of the log message.
 *
 * @return array|string
 *   If the ID is located in the Elasticsearch index, a build array in the
 *   format expected by drupal_render(); otherwise, an empty string.
 *
 * @see elasticsearch_watchdog_menu()
 */
function elasticsearch_watchdog_event($id) {
  $severity = watchdog_severity_levels();
  $client_id = elasticsearch_watchdog_get_cluster_id();
  if (!empty($client_id)) {
    $client = elasticsearch_connector_get_client_by_id($client_id);
    if ($client) {
      $params = array();
      $params['index'] = elasticsearch_watchdog_get_index_name();
      $params['type']  = elasticsearch_watchdog_get_type_name_for_view();
      $params['id']    = $id;

      if ($client->exists($params)) {
        $result = $client->get($params);
        if (!empty($result['_id'])) {
          $source = $result['_source'];
          $account = user_load($source['uid']);
          $rows = array(
            array(
              array('data' => t('Type'), 'header' => TRUE),
              $source['type'],
            ),
            array(
              array('data' => t('Date'), 'header' => TRUE),
              format_date($source['timestamp'], 'long'),
            ),
            array(
              array('data' => t('User'), 'header' => TRUE),
              theme('username', array('account' => $account)),
            ),
            array(
              array('data' => t('Location'), 'header' => TRUE),
              l($source['location'], $source['location']),
            ),
            array(
              array('data' => t('Referrer'), 'header' => TRUE),
              l($source['referer'], $source['referer']),
            ),
            array(
              array('data' => t('Message'), 'header' => TRUE),
              theme('elasticsearch_watchdog_message', array('event_id' => $id, 'event' => $source)),
            ),
            array(
              array('data' => t('Severity'), 'header' => TRUE),
              $severity[$source['severity']],
            ),
            array(
              array('data' => t('Hostname'), 'header' => TRUE),
              check_plain($source['hostname']),
            ),
            array(
              array('data' => t('Domain'), 'header' => TRUE),
              check_plain($source['domain']),
            ),
            array(
              array('data' => t('Operations'), 'header' => TRUE),
              $source['link'],
            ),
          );
          $build['elasticsearch_watchdog_table'] = array(
            '#theme' => 'table',
            '#rows' => $rows,
            '#attributes' => array('class' => array('elasticlog-event')),
          );
          return $build;
        }
        else {
          drupal_set_message(t('The Client ID does not exists in elasticsearch server.'), 'error');
        }
      }
      else {
        drupal_set_message(t("The log with ID: %id does not exist.", array('%id' => $id)), 'error');
      }
    }
  }
  else {
    drupal_set_message(t('Elasticsearch connector not specified.'), 'error');
  }

  drupal_goto('admin/reports/elasticlog');
}

/**
 * Builds a filter for elasticsearch log administration filters based on session.
 *
 * @return array
 *   An associative array with keys. Only filter for now 'filter'.
 */
function elasticsearch_watchdog_build_filter_query() {
  if (empty($_SESSION['elasticsearch_watchdog_overview_filter'])) {
    return;
  }

  $filters = elasticsearch_watchdog_filters(FALSE);
  $query_filter = array();
  $query = array();

  foreach ($_SESSION['elasticsearch_watchdog_overview_filter'] as $key => $filter) {
    $has_value = FALSE;
    $filter_where = array();

    if (empty($filters[$key]['type'])) {
      foreach ($filter as $value) {
        $has_value = TRUE;
        $filter_where[] = $value;
      }

      if ($has_value) {
        $query_filter['and'][] = array('terms' => array($filters[$key]['elastic_key'] => $filter_where));
      }
    }
    elseif ($filters[$key]['type'] == 'textfield' && !empty($filter) && is_scalar($filter)) {
      $query['bool']['must'][] = array('match' => array($filters[$key]['elastic_key'] => $filter));
    }
  }

  if (empty($query)) {
    $query['match_all'] = array();
  }

  $result = array('query' => $query, 'filter' => $query_filter);
  return $result;
}

/**
 * Creates a list of log administration filters that can be applied.
 *
 * @return array
 *   Associative array of filters. The top-level keys are used as the form
 *   element names for the filters, and the values are arrays with the following
 *   elements:
 *   - title: Title of the filter.
 *   - where: The filter condition.
 *   - options: Array of options for the select list for the filter.
 */
function elasticsearch_watchdog_filters($load_options = TRUE) {
  $filters = array();

  $filters['full_massage'] = array(
    'title' => t('Freetext'),
    'elastic_key' => "full_massage",
    'type'  => 'textfield',
    'options' => array(),
  );

  $types = array();
  foreach (_elasticsearch_watchdog_get_facets('type', 'terms') as $key => $value) {
    $types[$key] = t($value['key'] . ' (' . $value['doc_count'] . ')');
  }
  if (!empty($types) || $load_options == FALSE) {
    $filters['type'] = array(
      'title' => t('Type'),
      'elastic_key' => "type",
      'options' => $types,
    );
  }

  $severities = array();
  $basic_severity = watchdog_severity_levels();
  foreach (_elasticsearch_watchdog_get_facets('severity', 'terms') as $key => $value) {
    $severities[$key] = $basic_severity[$key] . ' (' . $value['doc_count'] . ')';
  }

  if (!empty($severities) || $load_options == FALSE) {
    $filters['severity'] = array(
      'title' => t('Severity'),
      'elastic_key' => 'severity',
      'options' => $severities,
    );
  }

  $options = array();
  foreach (_elasticsearch_watchdog_get_facets('domain', 'terms') as $key => $value) {
    $options[$key] = t($value['key'] . ' (' . $value['doc_count'] . ')');
  }
  if (!empty($options) || $load_options == FALSE) {
    $filters['domain'] = array(
      'title' => t('Domain'),
      'elastic_key' => "domain",
      'options' => $options,
    );
  }

  $options = array();
  foreach (_elasticsearch_watchdog_get_facets('username', 'terms') as $key => $value) {
    $options[$key] = t($value['key'] . ' (' . $value['doc_count'] . ')');
  }
  if (!empty($options) || $load_options == FALSE) {
    $filters['username'] = array(
      'title' => t('Username'),
      'elastic_key' => "username",
      'options' => $options,
    );
  }
  return $filters;
}

/**
 * Returns HTML for a log message.
 *
 * @param array $variables
 *   An associative array containing:
 *   - event_id: An object id.
 *   - event: An object with at least the message and variables properties.
 *   - link: (optional) Format message as link, event->wid is required.
 *
 * @ingroup themeable
 */
function theme_elasticsearch_watchdog_message($variables) {
  $output = '';
  $event = $variables['event'];
  // Check for required properties.
  if (isset($event['message']) && isset($event['variables'])) {
    // Messages without variables or user specified text.
    if ($event['variables'] === 'N;') {
      $output = $event['message'];
    }
    // Message to translate with injected variables.
    else {
      $output = t($event['message'], unserialize($event['variables']));
    }
    if ($variables['link'] && isset($variables['event_id'])) {
      // Truncate message to 56 chars.
      $output = truncate_utf8(filter_xss($output, array()), 56, TRUE, TRUE);
      $output = l($output, 'admin/reports/elasticlog/elastic-message/' . $variables['event_id'], array('html' => TRUE));
    }
  }
  return $output;
}

/**
 * Form constructor for the logging filter form.
 *
 * @see elasticsearch_watchdog_filter_form_validate()
 * @see elasticsearch_watchdog_filter_form_submit()
 * @see elasticsearch_watchdog_overview()
 *
 * @ingroup forms
 */
function elasticsearch_watchdog_filter_form($form) {
  $filters = elasticsearch_watchdog_filters();

  $form['filters'] = array(
    '#type' => 'fieldset',
    '#title' => t('Filter log messages'),
    '#collapsible' => TRUE,
    '#collapsed' => empty($_SESSION['elasticsearch_watchdog_overview_filter']),
  );

  foreach ($filters as $key => $filter) {
    if (empty($filter['type'])) {
      $form['filters']['status'][$key] = array(
        '#title' => check_plain($filter['title']),
        '#type' => 'select',
        '#multiple' => TRUE,
        '#size' => 8,
        '#options' => $filter['options'],
      );
    }
    else {
      $form['filters']['status'][$key] = array(
        '#title' => check_plain($filter['title']),
        '#type' => $filter['type'],
        '#size' => 30,
      );
    }

    if (!empty($_SESSION['elasticsearch_watchdog_overview_filter'][$key])) {
      $form['filters']['status'][$key]['#default_value'] = $_SESSION['elasticsearch_watchdog_overview_filter'][$key];
    }
  }

  $form['filters']['actions'] = array(
    '#type' => 'actions',
    '#attributes' => array('class' => array('container-inline')),
  );
  $form['filters']['actions']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Filter'),
  );
  if (!empty($_SESSION['elasticsearch_watchdog_overview_filter'])) {
    $form['filters']['actions']['reset'] = array(
      '#type' => 'submit',
      '#value' => t('Reset')
    );
    $form['filters']['actions']['delete'] = array(
      '#type' => 'submit',
      '#value' => t('Delete logs from index by current filter'),
      '#submit' => array('elasticsearch_watchdog_filter_delete_doc_submit')
    );
  }
  return $form;
}

/**
 * Form validation handler for elasticsearch_watchdog_filter_form().
 *
 * @see elasticsearch_watchdog_filter_form_submit()
 */
function elasticsearch_watchdog_filter_form_validate($form, &$form_state) {
  if ($form_state['values']['op'] == t('Filter')  && empty($form_state['values']['type']) && empty($form_state['values']['severity'])
      && empty($form_state['values']['domain']) && empty($form_state['values']['full_massage'])) {
    form_set_error('type', t('You must select something to filter by.'));
  }
}

/**
 * Form submission handler for elasticsearch_watchdog_filter_form().
 *
 * @see elasticsearch_watchdog_filter_form_validate()
 */
function elasticsearch_watchdog_filter_form_submit($form, &$form_state) {
  $op = $form_state['values']['op'];
  $filters = elasticsearch_watchdog_filters(FALSE);
  switch ($op) {
    case t('Filter'):
      foreach ($filters as $name => $filter) {
        if (isset($form_state['values'][$name])) {
          $_SESSION['elasticsearch_watchdog_overview_filter'][$name] = $form_state['values'][$name];
        }
      }
      break;
    case t('Reset'):
      $_SESSION['elasticsearch_watchdog_overview_filter'] = array();
      break;
  }
  return 'admin/reports/elasticlog';
}

/**
 * Form submission handler for delete button at elasticsearch_watchdog_filter_form
 * performs a redirect to the confirm_form
 *
 * @see  elasticsearch_watchdog_filter_form_validate()
 */
function elasticsearch_watchdog_filter_delete_doc_submit($form, &$form_state) {
  $destination = array();
  if (isset($_GET['destination'])) {
    $destination = drupal_get_destination();
    unset($_GET['destination']);
  }
  $form_state['redirect'] = array('admin/reports/elasticlog/delete-filter', array('query' => $destination));
}

/**
 * Confirm_form for deleting watchdog_docs from the index
 *
 * @see elasticsearch_watchdog_filter_delete_confirm_submit()
 *
 * @ingroup forms
 */
function elasticsearch_watchdog_filter_delete_confirm($form, &$form_state) {
  return confirm_form($form,
    t('Are you sure you want to delete the filtered logs from index?'),
    'admin/reports/elasticlog',
    t('This action cannot be undone.'),
    t('Delete'),
    t('Cancel')
  );
}

/**
 * Form submission handler for confirm_form
 * @param  [type] $form       [description]
 * @param  [type] $form_state [description]
 * @return [type]             [description]
 */
function elasticsearch_watchdog_filter_delete_confirm_submit($form, &$form_state) {
  if ($form_state['values']['confirm']) {

    if (empty($_SESSION['elasticsearch_watchdog_overview_filter'])) {
      drupal_set_message(t('No filter is present. Nothing was deleted.'));
    }
    else {
      $client_id = elasticsearch_watchdog_get_cluster_id();
      $index = elasticsearch_watchdog_get_index_name();
      $type = elasticsearch_watchdog_get_type_name_for_view();
      if (!empty($client_id)) {
        $client = elasticsearch_connector_get_client_by_id($client_id);
        if ($client) {
          try {
            $deleteParams = array();
            $deleteParams['index'] = $index;
            $deleteParams['type'] = $type;
            // Get the filter.
            $filter = elasticsearch_watchdog_build_filter_query();

            if (!empty($filter)) {
              $deleteParams['body']['query']['filtered'] = $filter;
            }
            $retDelete = $client->deleteByQuery($deleteParams);
            // Reset the filters cause when deleting a document the filters are not there anymore.
            $_SESSION['elasticsearch_watchdog_overview_filter'] = array();
            drupal_set_message(t('Selected Elasticsearch logs have been deleted.'));
          }
          catch (Exception $e) {
            drupal_set_message($e->getMessage(), 'error');
          }
        }
      }
    }
  }

  $form_state['redirect'] = 'admin/reports/elasticlog';
}


/**
 * Form constructor for the form that clears out the log.
 *
 * @see elasticsearch_watchdog_clear_log_submit()
 * @ingroup forms
 */
function elasticsearch_watchdog_clear_log_form($form) {
  $form['elasticsearch_watchdog_clear'] = array(
    '#type' => 'fieldset',
    '#title' => t('Clear log messages'),
    '#description' => t('This will permanently remove the log messages from the Elasticsearch index.'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );
  $form['elasticsearch_watchdog_clear']['clear'] = array(
    '#type' => 'submit',
    '#value' => t('Clear log messages'),
    '#submit' => array('elasticsearch_watchdog_clear_log_submit'),
  );

  return $form;
}

/**
 * Create Elasticsearch watchdog type.
 */
function elasticsearch_watchdog_create_type(\nodespark\DESConnector\ClientInterface $client, $index, $type) {
  $mapping_params['index'] = $index;
  $mapping_params['type'] = $type;
  $my_type_mapping = elasticsearch_watchdog_get_mapping();
  $mapping_params['body'][$type] = $my_type_mapping;
  $client->indices()->putMapping(
      $mapping_params
  );
}

/**
 * Form submission handler for elasticsearch_watchdog_clear_log_form().
 */
function elasticsearch_watchdog_clear_log_submit() {
  $_SESSION['elasticsearch_watchdog_overview_filter'] = array();
  // Delete the index and recreate it.
  $client_id = elasticsearch_watchdog_get_cluster_id();
  $index = elasticsearch_watchdog_get_index_name();
  $type = elasticsearch_watchdog_get_type_name_for_view();
  if (!empty($client_id)) {
    $client = elasticsearch_connector_get_client_by_id($client_id);
    if ($client) {
      try {
        $result = $client->indices()->deleteMapping(array(
          'index' => $index,
          'type'  => $type
        ));

        // Create the type if not exists.
        elasticsearch_watchdog_create_type($client, $index, $type);

        drupal_set_message(t('Elasticsearch logs has been cleared.'));
      }
      catch (Exception $e) {
        drupal_set_message($e->getMessage(), 'error');
      }
    }
  }
}
