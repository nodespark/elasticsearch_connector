<?php

/**
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
    //$this->params['fields'] = array_keys($view->field);
    $this->params['fields'] = array('_source');

    $params = array();
    if (isset($this->params['q'])) {
      // TODO: Build the query match.
      $params['match'] = $this->params['q'];
    }

    $params['size'] = $this->pager->options['items_per_page'];
    $params['from'] = $this->pager->current_page * $this->pager->options['items_per_page'];

    // If we display all items without pager.
    // TODO: Hardcoded 100000 doesn't seems correctly.
    if ($params['size'] == 0) {
      $params['size'] = 100000;
    }

    // Add fields.
    // We are specifying which fields to be visible!
    $params['fields'] = array();
    if (isset($this->params['fields'])) {
      $params['fields'] = array_merge($params['fields'], $this->params['fields']);
    }

    $where = $this->where;
    //Remove any empty conditions (exposed filters), they will cause an error
    foreach($where as &$where_condition){
      foreach($where_condition['conditions'] as $index => $condition){
        if ($condition['value'] == '') {
          unset($where_condition['conditions'][$index]);
        }
      }
    }
    // Add conditions to filter parameter.
    $conditions = array('conditions' => $where, 'type' => '');
    $conditions_string = $this->build_where_string($conditions);

    if (!empty($conditions_string)) {
      $params['fq'] = $conditions_string;
    }

    // Set query type if it is present.
    if (isset($this->params['defType'])) {
      $params['defType'] = $this->params['defType'];
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
    $cluster_id = $base_table_parts[0];
    $index = $base_table_parts[1];
    $type = $base_table_parts[2];

    // TODO: Make it Elasticsearch compatible.
    try {
      $start = microtime(TRUE);
      $client = elasticsearch_connector_get_client_by_id($cluster_id);
      if ($client) {
        $view->execute_time = microtime(TRUE) - $start;
        // Execute the search.
        // Add sorting.
        if (isset($this->params['sort'])) {
          // TODO: Build the sorting parameter.
          //$query->setAvailableSort($this->params['sort'][0], $this->params['sort'][1]);
          //$query->setSolrsort($this->params['sort'][0], $this->params['sort'][1]);
        }
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
          $view->result[] = (object)$doc['_source'];
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
        drupal_set_message($msg, 'error');
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
    $this->params['q.alt'][] = $string;
  }

  public function add_sort($field, $order) {
    $this->params['sort'] = array($field, $order);
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

  /**
   *  Build filter string from where array.
   */
  function build_where_string($where) {
//     if (!isset($where['conditions'])) {
//       return array($where['field'] => $where['value']);
//     }
//     else {
//       $condition_strings = array();
//       foreach ($where['conditions'] as $condition) {
//         $condition_strings[] = $this->build_where_string($condition);
//       }
//       $condition_strings = array_filter($condition_strings);
//       return implode(' ' . $where['type'] . ' ', $condition_strings);
//     }
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
