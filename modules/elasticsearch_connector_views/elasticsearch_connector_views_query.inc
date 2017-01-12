<?php
/**
 * @file
 * Class for handling a view that gets its data not from the database, but from
 * a Solr server.
 */
class elasticsearch_connector_views_query extends views_plugin_query {

  /**
   * Array of parameters for Solr query.
   */
  protected $params;
  protected $query_params;

  /**
   * Array of all encountered errors. Each of these is fatal, meaning that a
   * non-empty $errors property will result in an empty result being returned.
   *
   * @var array
   */
  protected $errors;

  /**
   * Array of where conditions.
   *
   * Neede for grouppin of query conditions.
   */
  protected $where = array();

  /**
   * Array holding sort fields and their order (ASC or DESC), to avoid duplication
   */
  protected $sort_fields = array();

  public function build(&$view) {
    $view->init_pager();

    // Let the pager modify the query to add limits.
    $this->pager->query();

    // Set aliases of the fields.
    foreach ($view->field as $field_name => &$field) {
      $field->field_alias = $field_name;
      $field->aliases['entity_type'] = 'entity_type';
    }

    // Add fields to the query so they will be shown in solr document.
    $this->params['fields'] = array_keys($view->field);
    $this->params['fields'][] = '_source';

    $params = array();
    if (isset($this->params['q']) && !empty($this->params['q'])) {

      // If we have more than one field we make a multi match query
      if (count($this->params['fields']) > 1) {

        $params['query']['multi_match'] = array(
                                            'query' => $this->params['q'],
                                            'fields' => array_values($this->params['fulltext_fields'])
                                          );
      }
      else {

        $params['query']['match'] = array(
                                      reset($this->params['fulltext_fields']) => array(
                                        'query' => $this->params['q'],
                                        'operator' => $this->params['fulltext_operator'],
                                      ),
                                    );
      }
    }

    $params['size'] = $this->pager->options['items_per_page'];
    $params['from'] = $this->pager->current_page * $this->pager->options['items_per_page'];

    // If we display all items without pager remove the size limit to return
    // all documents from elasticsearch.
    if ($params['size'] == 0) {
      unset($params['size']);
    }

    // Add fields.
    // We are specifying which fields to be visible!
    $params['fields'] = array();
    if (isset($this->params['fields'])) {
      $params['fields'] = array_merge($params['fields'], $this->params['fields']);
    }

    $where = $this->where;

    $params['filter'] = $this->build_filter_array($where);

    // Elastic complains when there is an empty filter array
    if (empty($params['filter'])) {
      unset($params['filter']);
    }

    // if a filter and query is set, combine them into a filtered query
    if (isset($params['filter']) && isset($params['query'])) {
      $temp = $params['query'];
      unset($params['query']);

      $params['query']['filtered'] = array(
        'query' => $temp,
        'filter' => $params['filter'],
      );

      unset($params['filter']);
    }

    // Add sorting.
    if (!empty($this->sort_fields)) {
      $params['sort'] = $this->build_sort_array();
    }

    $this->query_params = $params;

    // Export parameters for preview.
    $view->build_info['query'] = var_export($params, TRUE);
  }

  /**
   * Let modules modify the query just prior to finalizing it.
   */
  function alter(&$view) {
    foreach (module_implements('views_query_alter') as $module) {
      $function = $module . '_views_query_alter';
      $function($view, $this);
    }
  }

  /**
   * Executes the query and fills the associated view object with according
   * values.
   *
   * Values to set: $view->result, $view->total_rows, $view->execute_time,
   * $view->pager['current_page'].
   */
  public function execute(&$view) {
    $view->result = array();
    $view->total_rows = 0;
    $view->execute_time = 0;

    $base_table_parts = explode('__', $view->base_table);
    $cluster_id = $base_table_parts[1];
    $index = $base_table_parts[2];
    $type = $base_table_parts[3];

    try {
      $start = microtime(TRUE);
      $client = elasticsearch_connector_get_client_by_id($cluster_id);
      if ($client) {
        $view->execute_time = microtime(TRUE) - $start;
        // Execute the search.

      }

      // Execute search.
      $response = $client->search(array(
        'index' => $index,
        'type'  => $type,
        'body'  => $this->query_params
      ));

      // Store results.
      if (!empty($response['hits']['hits'])) {
        foreach ($response['hits']['hits'] as $doc) {
          $result_doc = array();
          foreach ($doc['fields'] as $field_name => $field_value) {
            // Handle multivalue with concatenation for now.
            $result_doc[$field_name] = implode(' | ', $field_value);
          }
          $result_doc['_source'] = $doc['_source'];
          $view->result[] = (object)$result_doc;
        }
      }

      // Store response into the object.
      $this->response = $response;

      // Store the results.
      $this->pager->total_items = $view->total_rows = $response['hits']['total'];
      $this->pager->update_page_info();

      // We shouldn't use $results['performance']['complete'] here, since
      // extracting the results probably takes considerable time as well.
      $view->execute_time = $response['took'];
    }
    catch (Exception $e) {
      $this->errors[] = $e->getMessage();
    }

    if ($this->errors) {
      foreach ($this->errors as $msg) {
        drupal_set_message(check_plain($msg), 'error');
      }
      $view->result = array();
      $view->total_rows = 0;
      $view->execute_time = 0;
      return;
    }
  }

  public function add_filter($type, $value, $exclude = FALSE) {
    $exclude_string = ($exclude) ? '-' : '';
    $this->params['filters'][] = $exclude_string . $type . ':(' . $value . ')';
  }

  public function add_filter_string($string) {
    // TODO: Rework if necessary.
    $this->params['q.alt'][] = $string;
  }

  public function add_sort($field, $order) {
    if (!isset($this->sort_fields[$field]) || $this->sort_fields[$field] != drupal_strtolower($order)) {
      $this->sort_fields[$field] = drupal_strtolower($order);
    }
  }

  public function build_sort_array() {
    $sort = array();

    foreach ($this->sort_fields as $field => $order) {
      $sort[] = array($field => $order);
    }

    return $sort;
  }

  public function add_parameter($key, $value) {
    $this->params[$key] = $value;
  }

  public function add_field($table_alias, $field, $alias = '', $params = array()) {
    if (isset($table_alias[$field])) {
      return $table_alias[$field];
    }
  }

  public function get_params() {
    return $this->params;
  }

  function build_filter_array($where) {
    $filter = array();
    foreach ($where as $wh) {
      foreach ($wh['conditions'] as $cond) {
        $filter[drupal_strtolower($wh['type'])][] = $cond['field'];
      }
    }

    if (count($filter) > 1) {
      $filter = array(drupal_strtolower($this->group_operator) => $filter);
    }

    return $filter;
  }

  /**
   * Support for groupping.
   *
   * @see views_plugin_query_default::add_where().
   */
  function add_where($group, $field, $value = NULL, $operator = NULL) {
    // Ensure all variants of 0 are actually 0. Thus '', 0 and NULL are all
    // the default group.
    if (empty($group)) {
      $group = 0;
    }

    // Check for a group.
    if (!isset($this->where[$group])) {
      $this->set_where_group('AND', $group);
    }

    $this->where[$group]['conditions'][] = array(
      'field' => $field,
      'value' => $value,
      'operator' => $operator,
    );
  }

  /**
   * Support for groupping.
   *
   * @see views_plugin_query_default::set_where_group().
   */
  function set_where_group($type = 'AND', $group = NULL, $where = 'where') {
    // Set an alias.
    $groups = &$this->$where;

    if (!isset($group)) {
      $group = empty($groups) ? 1 : max(array_keys($groups)) + 1;
    }

    // Create an empty group
    if (empty($groups[$group])) {
      $groups[$group] = array('conditions' => array(), 'args' => array());
    }

    $groups[$group]['type'] = strtoupper($type);
    return $group;
  }
}
