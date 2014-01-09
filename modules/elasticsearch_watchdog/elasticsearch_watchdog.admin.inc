<?php
/**
 * @file
 * Created on Jan 06, 2014
 *
 * TODO: Think of setting a TTL to the messages that will cause automatic deleteing of messages.
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

  $clusters = elasticsearch_connector_cluster_load_all(TRUE, TRUE);
  $form['#old_cluster_id'] = variable_get('elasticsearch_watchdog_cluster_id', '');

  $form['elasticsearch_watchdog_cluster_id'] = array(
    '#type' => 'select',
    '#title' => t('Select cluster'),
    '#required' => TRUE,
    '#default_value' => variable_get('elasticsearch_watchdog_cluster_id', ''),
    '#options' => $clusters,
    '#description' => t('Select the cluster you want to hold the logs.')
  );

  $form['elasticsearch_watchdog_num_of_shards'] = array(
    '#type' => 'textfield',
    '#title' => t('Number of shards'),
    '#required' => TRUE,
    '#default_value' => variable_get('elasticsearch_watchdog_num_of_shards', ELASTICSEARCH_WATCHDOG_DEFAULT_SHARDS),
    '#description' => t('Enter the number of shards for the index.')
  );

  $form['elasticsearch_watchdog_num_of_replica'] = array(
    '#type' => 'textfield',
    '#title' => t('Number of replica'),
    '#default_value' => variable_get('elasticsearch_watchdog_num_of_replica', ELASTICSEARCH_WATCHDOG_DEFAULT_REPLICA),
    '#description' => t('Enter the number of shards replicas.')
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
  $client = elasticsearch_connector_get_client_by_id($values['elasticsearch_watchdog_cluster_id']);

  $index_name = elasticsearch_watchdog_get_realindex_name();
  $alias_name = elasticsearch_watchdog_get_index_name();
  $type_name = elasticsearch_watchdog_get_type_name();

  if ($client) {
    $update_settings = TRUE;
    if (!$client->indices()->exists(array('index' => $index_name))) {
      $update_settings = FALSE;
      $result = elasticsearch_watchdog_create_index_and_mapping($client,
                    $values['elasticsearch_watchdog_num_of_shards'],
                    $values['elasticsearch_watchdog_num_of_replica']);

      if (empty($result['ok'])) {
        form_set_error('elasticsearch_watchdog_cluster_id', $result['error']);
      }
    }
    elseif (!$client->indices()->existsType(array('index' => $index_name, 'type' => $type_name))) {
      elasticsearch_watchdog_create_type($client, $index_name, $type_name);
    }

    if ($update_settings) {
      $settings['settings']['index.number_of_replicas'] = $values['elasticsearch_watchdog_num_of_replica'];
      $settings = $client->indices()->putSettings(
        array(
          'index' => $index_name,
          'body' => $settings,
        )
      );
    }

  }
  else {
    form_set_error('elasticsearch_watchdog_cluster_id', t('Cannot connect to the cluster for some reason! Please contact your system administrator.'));
  }
}

function elasticsearch_watchdog_get_mapping() {
  // Index Mapping
  $myTypeMapping = array(
    '_source' => array(
      'enabled' => TRUE
    ),
    "_all" => array(
      'enabled' => FALSE
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

  return $myTypeMapping;
}

/**
 *
 * @param object $client
 * @return mixed $result from Elasticsearch\Client object.
 */
function elasticsearch_watchdog_create_index_and_mapping($client, $number_of_shards = NULL, $number_of_replica = NULL) {

  $index_name = elasticsearch_watchdog_get_realindex_name();
  $alias_name = elasticsearch_watchdog_get_index_name();
  $type = elasticsearch_watchdog_get_type_name();
  if (!isset($number_of_shards)) {
    $number_of_shards = variable_get('elasticsearch_watchdog_num_of_shards', ELASTICSEARCH_WATCHDOG_DEFAULT_SHARDS);
  }

  if (!isset($number_of_replica)) {
    $number_of_replica = (int)variable_get('elasticsearch_watchdog_num_of_replica', ELASTICSEARCH_WATCHDOG_DEFAULT_REPLICA);
  }

  // Index
  $indexParams['index']  = $index_name;
  // Index Settings
  $indexParams['body']['settings']['number_of_shards']   = $number_of_shards;
  $indexParams['body']['settings']['number_of_replicas'] = $number_of_replica;
  $myTypeMapping = elasticsearch_watchdog_get_mapping();
  $indexParams['body']['mappings'][$type] = $myTypeMapping;

  try {
    $template_body = $indexParams['body'];
    $template_body['template'] = $alias_name . '*';

    $client->indices()->putTemplate(array(
      'name' => $alias_name . '_template',
      'body' => $template_body,
    ));

    $result = $client->indices()->create($indexParams);

    $client->indices()->putAlias(array(
      'name' => $alias_name,
      'index' => $index_name,
    ));
  }
  catch (Exception $e) {
    error_log($e->getMessage());
  }

  return $result;
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

  $client_id = variable_get('elasticsearch_watchdog_cluster_id', '');
  if (!empty($client_id)) {
    $client = elasticsearch_connector_get_client_by_id($client_id);
    if ($client) {
      try {
        $params = array();
        $index_name = elasticsearch_watchdog_get_index_name();
        $params['index'] = $index_name;
        $params['type']  = elasticsearch_watchdog_get_type_name();

        if ($client->indices()->exists(array('index' => $index_name)) && $client->indices()->existsType($params)) {
          $sort = tablesort_get_sort($header);
          $sort_fields = tablesort_get_order($header);
          $limit = variable_get('elasticsearch_watchdog_page_limit', 50);
          $current_page = pager_find_page();
          $params['body']['size']  = $limit;
          $params['body']['from']  = $current_page*$limit;

          foreach($sort_fields['sql'] as $sort_field) {
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
        drupal_set_messsage($e->getMessage(), 'error');
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
  $client_id = variable_get('elasticsearch_watchdog_cluster_id', '');
  if (!empty($client_id)) {
    $client = elasticsearch_connector_get_client_by_id($client_id);
    if ($client) {
      try {
        $params = array();
        $params['index'] = elasticsearch_watchdog_get_index_name();
        $params['type']  = elasticsearch_watchdog_get_type_name();
        $params['search_type'] = 'count';
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
  $client_id = variable_get('elasticsearch_watchdog_cluster_id', '');
  if (!empty($client_id)) {
    $client = elasticsearch_connector_get_client_by_id($client_id);
    if ($client) {
      $params = array();
      $params['index'] = elasticsearch_watchdog_get_index_name();
      $params['type']  = elasticsearch_watchdog_get_type_name();
      $params['id']    = $id;

      $result = $client->get($params);
      if (!empty($result['exists'])) {
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
    }
  }
  else {
    return '';
  }
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
    $types[$key] = t($value['term'] . ' (' . $value['count'] . ')');
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
  foreach(_elasticsearch_watchdog_get_facets('severity', 'terms') as $key => $value) {
    $severities[$key] = $basic_severity[$key] . ' (' . $value['count'] . ')';
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
    $options[$key] = t($value['term'] . ' (' . $value['count'] . ')');
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
    $options[$key] = t($value['term'] . ' (' . $value['count'] . ')');
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
        '#title' => $filter['title'],
        '#type' => 'select',
        '#multiple' => TRUE,
        '#size' => 8,
        '#options' => $filter['options'],
      );
    }
    else {
      $form['filters']['status'][$key] = array(
        '#title' => $filter['title'],
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
  }
  return $form;
}

/**
 * Form validation handler for elasticsearch_watchdog_filter_form().
 *
 * @see elasticsearch_watchdog_filter_form_submit()
 */
function elasticsearch_watchdog_filter_form_validate($form, &$form_state) {
  if ($form_state['values']['op'] == t('Filter') && empty($form_state['values']['type']) && empty($form_state['values']['severity'])
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
function elasticsearch_watchdog_create_type(Elasticsearch\Client $client, $index, $type) {
  $mappingParams['index'] = $index;
  $mappingParams['type'] = $type;
  $myTypeMapping = elasticsearch_watchdog_get_mapping();
  $mappingParams['body'][$type] = $myTypeMapping;
  $client->indices()->putMapping(
      $mappingParams
  );
}

/**
 * Form submission handler for elasticsearch_watchdog_clear_log_form().
 */
function elasticsearch_watchdog_clear_log_submit() {
  $_SESSION['elasticsearch_watchdog_overview_filter'] = array();
  // Delete the index and recreate it.
  $client_id = variable_get('elasticsearch_watchdog_cluster_id', '');
  $index = elasticsearch_watchdog_get_index_name();
  $type = elasticsearch_watchdog_get_type_name();
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
      catch(Exception $e) {
        drupal_set_message($e->getMessage(), 'error');
      }
    }
  }
}
