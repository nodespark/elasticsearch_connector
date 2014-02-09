<?php

/**
 * @file
 * Provides a Elasticsearch-based service class for the Search API using
 * Elasticsearch Connector module.
 */

/**
 * Search service class.
 */
class SearchApiElasticsearchConnector extends SearchApiAbstractService {

  /**
   * Elasticsearch Connection.
   */
  protected $elasticsearchClient = NULL;
  private   $cluster_id = NULL;

  /**
   * Overrides __construct().
   */
  public function __construct(SearchApiServer $server) {
    parent::__construct($server);

    $this->cluster_id = $this->getOption('cluster', '');
    if ($this->cluster_id) {
      $this->elasticsearchClient = elasticsearch_connector_get_client_by_id($this->cluster_id);
    }
  }

  /**
   * Overrides configurationForm().
   */
  public function configurationForm(array $form, array &$form_state) {
    // Connector settings.
    $form['connector_settings'] = array(
      '#type' => 'fieldset',
      '#title' => t('Elasticsearch connector settings'),
      '#tree' => FALSE,
    );

    $clusters = elasticsearch_connector_cluster_load_all(TRUE, TRUE);
    $form['connector_settings']['cluster'] = array(
      '#type' => 'select',
      '#title' => t('Cluster'),
      '#required' => TRUE,
      '#default_value' => $this->getOption('cluster', ''),
      '#options' => $clusters,
      '#description' => t('Select the cluster you want to handle the connections.'),
      '#parents' => array('options', 'form', 'cluster'),
    );

    // Facet settings.
    $form['facet_settings'] = array(
      '#type' => 'fieldset',
      '#title' => t('Elasticsearch facet settings'),
      '#tree' => FALSE,
      '#access' => module_exists('search_api_facetapi'),
    );

    // Elasticsearch facet limit.
    $default = 10;
    $form['facet_settings']['facet_limit'] = array(
      '#type' => 'textfield',
      '#title' => t('Facet limit'),
      '#description' => t("Maximum number of facet elements to be returned by the server if 'no limit' is selected as hard limit is the facet option. Default is %default.", array(
        '%default' => $default,
      )),
      '#required' => TRUE,
      '#default_value' => $this->getOption('facet_limit', $default),
      '#parents' => array('options', 'form', 'facet_limit'),
    );

    return $form;
  }

  /**
   * Overrides configurationFormValidate().
   */
  public function configurationFormValidate(array $form, array &$values, array &$form_state) {
    $clusters = elasticsearch_connector_cluster_load_all(TRUE, TRUE);
    // Check cluster!
    if (empty($clusters[$values['cluster']])) {
      form_set_error('options][form][cluster', t('You must select a valid Cluster from the elasticsearch clusters dropdown.'));
    }

    // Facet limit.
    if (filter_var($values['facet_limit'], FILTER_VALIDATE_INT, array('options' => array('min_range' => 0))) === FALSE) {
      form_set_error('options][form][facet_limit', t('You must enter a positive integer for the elasticsearch facet limit.'));
    }
  }

  /**
   * Overrides supportsFeature().
   */
  public function supportsFeature($feature) {
    // First, check the features we always support.
    $supported = drupal_map_assoc(array(
      'search_api_autocomplete',
      'search_api_facets',
      'search_api_facets_operator_or',
      'search_api_mlt',
      // TODO: Implement the rest of the features available!
//       'search_api_grouping',
//       'search_api_multi',
//       'search_api_service_extra',
//       'search_api_spellcheck',
//       'search_api_data_type_location',
//       'search_api_data_type_geohash',
    ));
    return isset($supported[$feature]);
  }

  /**
   * Overrides postCreate().
   */
  public function postCreate() {
  }

  /**
   * Overrides postUpdate().
   */
  public function postUpdate() {
    return FALSE;
  }

  /**
   * Overrides preDelete().
   */
  public function preDelete() {
  }

  /**
   * Overrides viewSettings().
   */
  public function viewSettings() {
    $output = array();

    $status = !empty($this->elasticsearchClient) ? $this->elasticsearchClient->info() : NULL;
    $elasticsearch_connector_path = elasticsearch_connector_main_settings_path();
    $output['status'] = array(
      '#type' => 'item',
      '#title' => t('Elasticsearch cluster status'),
      '#markup' => '<div class="elasticsearch-daemon-status"><em>' . (!empty($status['ok']) ? 'running' : 'not running') . '</em>' .
                   ' - <a href=" ' . url($elasticsearch_connector_path . '/clusters/' . $this->cluster_id . '/info') .  ' ">' . t('More info') . '</a></div>',
    );

    // Display settings.
    $form = $form_state = array();
    $option_form = $this->configurationForm($form, $form_state);
    $option_form['#title'] = t('Elasticsearch server settings');

    $element = $this->parseOptionFormElement($option_form, 'options');
    if (!empty($element)) {
      $settings = '';
      foreach ($element['option'] as $sub_element) {
        $settings .= $this->viewSettingElement($sub_element);
      }

      $output['settings'] = array(
        '#type' => 'fieldset',
        '#title' => $element['label'],
      );

      $output['settings'][] = array(
        '#type' => 'markup',
        '#markup' => '<div class="elasticsearch-server-settings">' . $settings . '</div>',
      );
    }

    return $output;
  }

  /**
   * Helper function. Parse an option form element.
   */
  protected function parseOptionFormElement($element, $key) {
    $children_keys = element_children($element);

    if (!empty($children_keys)) {
      $children = array();
      foreach ($children_keys as $child_key) {
        $child = $this->parseOptionFormElement($element[$child_key], $child_key);
        if (!empty($child)) {
          $children[] = $child;
        }
      }
      if (!empty($children)) {
        return array(
          'label' => isset($element['#title']) ? $element['#title'] : $key,
          'option' => $children,
        );
      }
    }
    elseif (isset($this->options[$key])) {
      return array(
        'label' => isset($element['#title']) ? $element['#title'] : $key,
        'option' => $key,
      );
    }

    return array();
  }

  /**
   * Helper function. Display a setting element.
   */
  protected function viewSettingElement($element) {
    $output = '';

    if (is_array($element['option'])) {
      $value = '';
      foreach ($element['option'] as $sub_element) {
        $value .= $this->viewSettingElement($sub_element);
      }
    }
    else {
      $value = $this->getOption($element['option']);
      $value = nl2br(check_plain(print_r($value, TRUE)));
    }
    $output .= '<dt><em>' . check_plain($element['label']) . '</em></dt>' . "\n";
    $output .= '<dd>' . $value . '</dd>' . "\n";

    return "<dl>\n{$output}</dl>";
  }

  /**
   * Overrides addIndex().
   */
  public function addIndex(SearchApiIndex $index) {
    $index_name = $this->getIndexName($index);
    if (!empty($index_name)) {
      try {
        $response = $this->elasticsearchClient->indices()->create(array(
          'index' => $index_name,
          'body' => array(
            'settings' => array(
              'number_of_shards' => $index->options['number_of_shards'],
              'number_of_replicas' => $index->options['number_of_replicas'],
            )
          )
        ));
        if (empty($response['ok'])) {
          drupal_set_message(t('The elasticsearch client wasn\'t able to create index'), 'error');
        }
        // Update mapping.
        $this->fieldsUpdated($index);
      }
      catch (Exception $e) {
        drupal_set_message($e->getMessage(), 'error');
      }
    }
  }

  /**
   * Overrides fieldsUpdated().
   */
  public function fieldsUpdated(SearchApiIndex $index) {
    $params = $this->getIndexParam($index, TRUE);

    $properties = array(
      'id' => array('type' => 'integer', 'include_in_all' => FALSE),
    );

    // Map index fields.
    foreach ($index->getFields() as $field_id => $field_data) {
      $properties[$field_id] = $this->getFieldMapping($field_data);
    }

    try {
      if ($this->elasticsearchClient->indices()->existsType($params)) {
        $current_mapping = $this->elasticsearchClient->indices()->getMapping($params);
        if (!empty($current_mapping)) {
          // If the mapping exits, delete it to be able to re-create it.
          $this->elasticsearchClient->indices()->deleteMapping($params);
        }
      }

      $params['body'][$params['type']]['properties'] = $properties;
      $results = $this->elasticsearchClient->indices()->putMapping($params);
      if (empty($results['ok'])) {
        drupal_set_message(t('Cannot create the matting of the fields!'), 'error');
      }
    }
    catch (Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Helper function to return the index param.
   * @param SearchApiIndex $index
   * @return array
   */
  protected function getIndexParam(SearchApiIndex $index, $with_type = FALSE) {
    $index_name = $this->getIndexName($index);

    $params = array();
    $params['index'] = $index_name;

    if ($with_type) {
      $params['type'] = $index->machine_name;
    }

    return $params;
  }

  /**
   * Overrides removeIndex().
   */
  public function removeIndex($index) {
    $params = $this->getIndexParam($index);

    try {
      $response = $this->elasticsearchClient->indices()->delete($params);
    }
    catch (Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
    }
  }

  /**
   * Helper function, check if the type exists.
   * @param SearchApiIndex $index
   * @return boolean
   */
  protected function getElasticsearchTypeExists(SearchApiIndex $index) {
    $params = $this->getIndexParam($index, TRUE);
    try {
      return $this->elasticsearchClient->indices()->existsType($params);
    }
    catch (Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
      return FALSE;
    }
  }

  /**
   * Overrides indexItems().
   */
  public function indexItems(SearchApiIndex $index, array $items) {
    $elastic_type_exists = $this->getElasticsearchTypeExists($index);

    if (empty($elastic_type_exists) || empty($items)) {
      return array();
    }
    $params = $this->getIndexParam($index, TRUE);

    $documents = array();
    $params['refresh'] = TRUE;
    foreach ($items as $id => $fields) {
      $data = array('id' => $id);
      foreach ($fields as $field_id => $field_data) {
        $data[$field_id] = $field_data['value'];
      }

      $params['body'][] = array('index' => array('_id' => $data['id']));
      $params['body'][] = $data;
    }

    try {
      $this->elasticsearchClient->bulk($params);
    }
    catch (Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
    }

    return array_keys($items);
  }

  /**
   * Overrides deleteItems().
   */
  public function deleteItems($ids = 'all', SearchApiIndex $index = NULL) {
    if (empty($index)) {
      foreach ($this->getIndexes() as $index) {
        $this->deleteItems('all', $index);
      }
    }
    elseif ($ids === 'all') {
      // Faster to delete the index and recreate it.
      $this->removeIndex($index);
      $this->addIndex($index);
    }
    else {
      $this->deleteItemsIds($ids, $index);
    }
  }

  /**
   * Helper function for bulk delete operation.
   *
   * @param array $ids
   * @param SearchApiIndex $index
   *
   * TODO: Test function if working.
   *
   */
  private function deleteItemsIds($ids, SearchApiIndex $index = NULL) {
    $params = $this->getIndexParam($index, TRUE);
    foreach ($ids as $id) {
      $params['body'][] = array(
        'delete' => array(
          '_index' => $params['index'],
          '_type' => $params['type'],
          '_id' => $id,
        )
      );
    }

    try {
      $this->elasticsearchClient->bulk($params);
    }
    catch (Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
    }
  }


  /**
   * Overrides search().
   */
  public function search(SearchApiQueryInterface $query) {

    // Results.
    $search_result = array('result count' => 0);

    // Get index
    $index = $query->getIndex();

    $params = $this->getIndexParam($index, TRUE);

    // Check elasticsearch index.
    if (!$this->elasticsearchClient->indices()->existsType($params)) {
      return $search_result;
    }
    $query->setOption('ElasticParams', $params);

    // Build Elastica query.
    $params = $this->buildSearchQuery($query);

    // Add facets.
    $this->addSearchFacets($params, $query);

    // Do search.
    $response = $this->elasticsearchClient->search($params);

    // Parse response.
    return $this->parseSearchResponse($response, $query);
  }

  /**
   * Recursively parse Search API filters.
   */
  protected function parseFilter(SearchApiQueryFilter $query_filter, $index_fields, $ignored_field_id = '') {

    if (empty($query_filter)) {
      return NULL;
    }
    else {
      $conjunction = $query_filter->getConjunction();

      $filters = array();

      try {
        foreach ($query_filter->getFilters() as $filter_info) {
          $filter = NULL;

          // Simple filter [field_id, value, operator].
          if (is_array($filter_info)) {
            $filter_assoc = $this->getAssociativeFilter($filter_info);
            $this->correctFilter($filter_assoc, $index_fields, $ignored_field_id);
            // Check field.
            $filter = $this->getFilter($filter_assoc);

            if (!empty($filter)) {
              $filters[] = $filter;
            }
          }
          // Nested filters.
          elseif ($filter_info instanceof SearchApiQueryFilter) {
            $nested_filters = $this->parseFilter($filter_info, $index_fields, $ignored_field_id);
            // TODO: handle error. - here is unnecessary cause in if we thow exceptions and this is still in try{}  .
            if (!empty($nested_filters)) {
              $filters = array_merge($filters, $nested_filters);
            }
          }
        }
        $filters = $this->setFiltersConjunction($filters, $conjunction);
      }
      catch (Exception $e) {
        watchdog('Elasticsearch Search API', check_plain($e->getMessage()), array(), WATCHDOG_ERROR);
        drupal_set_message(check_plain($e->getMessage()), 'error');
      }

      return $filters;
    }
  }

  /**
   * Get filter by associative array.
   */
  protected function getFilter(array $filter_assoc) {
    // Handles "empty", "not empty" operators.
    if (!isset($filter_assoc['filter_value'])) {
      switch ($filter_assoc['filter_operator']) {
        case '<>':
          $filter = array(
            'exists' => array('field' => $filter_assoc['field_id'])
          );
          break;

        case '=':
          $filter = array(
            'not' => array(
              'filter' => array(
                'exists' => array('field' => $filter_assoc['field_id'])
              )
            )
          );
          break;

        default:
          throw new Exception(t('Value is empty for :field_id! Incorrect filter criteria is using for searching!', array(':field_id' => $filter_assoc['field_id'])));
      }
    }
    // Normal filters.
    else {
      switch ($filter_assoc['filter_operator']) {
        case '=':
          $filter = array(
            'term' => array($filter_assoc['field_id'] => $filter_assoc['filter_value'])
          );
          break;

        case '<>':
          $filter = array(
            'not' => array(
              'filter' => array(
                'term' => array($filter_assoc['field_id'] => $filter_assoc['filter_value'])
              )
            )
          );
          break;

        case '>':
          $filter = array(
            'range' => array(
              $filter_assoc['field_id'] => array(
                'from'          => $filter_assoc['filter_value'],
                'to'            => NULL,
                'include_lower' => FALSE,
                'include_upper' => FALSE
              )
            )
          );
          break;

        case '>=':
          $filter = array(
            'range' => array(
              $filter_assoc['field_id'] => array(
                'from'          => $filter_assoc['filter_value'],
                'to'            => NULL,
                'include_lower' => TRUE,
                'include_upper' => FALSE
              )
            )
          );
          break;

        case '<':
          $filter = array(
            'range' => array(
              $filter_assoc['field_id'] => array(
                'from'          => NULL,
                'to'            => $filter_assoc['filter_value'],
                'include_lower' => FALSE,
                'include_upper' => FALSE
              )
            )
          );
          break;

        case '<=':
          $filter = array(
            'range' => array(
              $filter_assoc['field_id'] => array(
                'from'          => NULL,
                'to'            => $filter_assoc['filter_value'],
                'include_lower' => FALSE,
                'include_upper' => TRUE
              )
            )
          );
          break;

        default:
          throw new Exception(t('Undefined operator :field_operator for :field_id field! Incorrect filter criteria is using for searching!',
          array(':field_operator' => $filter_assoc['filter_operator'], ':field_id' => $filter_assoc['field_id'])));
      }
    }

    return $filter;
  }

  /**
   * Helper function that return associative array  of filters info.
   */
  public function getAssociativeFilter(array $filter_info) {

    $filter_operator = str_replace('!=', '<>', $filter_info[2]);
    return array(
      'field_id' => $filter_info[0],
      'filter_value' => $filter_info[1],
      'filter_operator' => $filter_operator,
    );
  }

  /**
   * Helper function thaht set filters conjunction
   */
  protected function setFiltersConjunction(&$filters, $conjunction) {
    if (count($filters) > 1) {
      if ($conjunction === 'OR') {
        $filters = array(array('or' => $filters));
      }
      elseif ($conjunction === 'AND') {
        $filters = array(array('and' => $filters));
      }
      else {
        throw new Exception(t('Undefined conjunction :conjunction! Available values are :avail_conjunction! Incorrect filter criteria is using for searching!',
            array(':conjunction!' => $conjunction, ':avail_conjunction' => $conjunction)));
        return NULL;
      }
    }

    return $filters;
  }

  /**
   * Helper function that check if filter is set correct.
   */
  protected function correctFilter($filter_assoc, $index_fields, $ignored_field_id = '') {
    if (!isset($filter_assoc['field_id']) || !isset($filter_assoc['filter_value'])
    || !isset($filter_assoc['filter_operator'])) {
      // TODO: When using views the sort field is comming as a filter and messing with this section.
      // throw new Exception(t('Incorrect filter criteria is using for searching!'));
    }

    $field_id = $filter_assoc['field_id'];
    if (!isset($index_fields[$field_id])) {
      throw new Exception(t(':field_id Undefined field ! Incorrect filter criteria is using for searching!', array(':field_id' => $field_id)));
    }

    // Check operator.
    if (empty($filter_assoc['filter_operator'])) {
      throw new Exception(t('Empty filter operator for :field_id field! Incorrect filter criteria is using for searching!', array(':field_id' => $field_id)));
    }

    // If field should be ignored, we skip.
    if ($field_id === $ignored_field_id) {
      return TRUE;
    }

    return TRUE;
  }

  /**
   * Return a full text search query.
   *
   * TODO: better handling of parse modes.
   */
  protected function flattenKeys($keys, $parse_mode = '', $full_text_fields = array()) {
    $conjunction = isset($keys['#conjunction']) ? $keys['#conjunction'] : 'AND';
    $negation = !empty($keys['#negation']);
    $values = array();

    foreach (element_children($keys) as $key) {
      $value = $keys[$key];

      if (empty($value)) {
        continue;
      }

      if (is_array($value)) {
        $values[] = $this->flattenKeys($value);
      }
      elseif (is_string($value)) {
        // If parse mode is not "direct": quote the keyword.
        if ($parse_mode !== 'direct') {
          $value = '"' . $value . '"';
        }

        $values[] = $value;
      }
    }
    if (!empty($values)) {
      return ($negation === TRUE ? 'NOT ' : '') . '(' . implode(" {$conjunction} ", $values) . ')';
    }
    else {
      return '';
    }
  }

  /**
   * Helper function. Returns the elasticsearch name of an index.
   */
  public function getIndexName(SearchApiIndex $index) {
    global $databases;

    $site_database = $databases['default']['default']['database'];

    $index_machine_name = is_string($index) ? $index : $index->machine_name;

    return self::escapeName('elasticsearch_index_' . $site_database . '_' . $index_machine_name);
  }

  /**
   * Helper function. Escape a field or index name.
   *
   * Force names to be strictly alphanumeric-plus-underscore.
   */
  public static function escapeName($name) {
    return preg_replace('/[^A-Za-z0-9_]+/', '', $name);
  }

  /**
   * Helper function. Get the elasticsearch mapping for a field.
   */
  public function getFieldMapping($field) {
    $type = search_api_extract_inner_type($field['type']);

    switch ($type) {
    	case 'text':
    	  return array(
    	  'type' => 'string',
    	  'boost' => $field['boost'],
    	  );

    	case 'uri':
    	case 'string':
    	case 'token':
    	  return array(
    	  'type' => 'string',
    	  'index' => 'not_analyzed',
    	  );

    	case 'integer':
    	case 'duration':
    	  return array(
    	  'type' => 'integer',
    	  );

    	case 'boolean':
    	  return array(
    	  'type' => 'boolean',
    	  );

    	case 'decimal':
    	  return array(
    	  'type' => 'float',
    	  );

    	case 'date':
    	  return array(
    	  'type' => 'date',
    	  'format' => 'date_time',
    	  );

    	default:
    	  return NULL;
    }
  }

  /**
   * Helper function. Return date gap from two dates or timestamps.
   *
   * @see facetapi_get_timestamp_gap()
   */
  protected static function getDateGap($min, $max, $timestamp = TRUE) {
    if ($timestamp !== TRUE) {
      $min = strtotime($min);
      $max = strtotime($max);
    }

    if (empty($min) || empty($max)) {
      return 'DAY';
    }

    $diff = $max - $min;

    switch (TRUE) {
    	case ($diff > 86400 * 365):
    	  return 'NONE';

    	case ($diff > 86400 * gmdate('t', $min)):
    	  return 'YEAR';

    	case ($diff > 86400):
    	  return 'MONTH';

    	default:
    	  return 'DAY';
    }
  }

  /**
   * Helper function. Return server options.
   */
  public function getOptions() {
    return $this->options;
  }

  /**
   * Helper function. Return a server option.
   */
  public function getOption($option, $default = NULL) {
    $options = $this->getOptions();
    return isset($options[$option]) ? $options[$option] : $default;
  }

  /**
   * Helper function. Return index fields.
   */
  public function getIndexFields(SearchApiQueryInterface $query) {
    $index = $query->getIndex();
    $index_fields = $index->getFields();
    return $index_fields;
  }

  /**
   * Helper function build search query().
   */
  protected function buildSearchQuery(SearchApiQueryInterface $query) {
    // Query options.
    $query_options = $this->getSearchQueryOptions($query);

    // Main query.
    $params = $query->getOption('ElasticParams');
    $body = &$params['body'];

    // Set the size and from parameters.
    $body['from']  = $query_options['query_offset'];
    $body['size']  = $query_options['query_limit'];

    // Sort
    if (!empty($query_options['sort'])) {
      $body['sort'] = $query_options['sort'];
    }

    $body['fields'] = array();
    $fields = &$body['fields'];

    // More Like This
    if (!empty($query_options['mlt'])) {
      $mlt_query['more_like_this'] = array();
      $mlt_query['more_like_this']['like_text'] = $query_options['mlt']['id'];
      $mlt_query['more_like_this']['fields'] = array_values($query_options['mlt']['fields']);
      // TODO: Make this settings configurable in the view.
      $mlt_query['more_like_this']['max_query_terms'] = 1;
      $mlt_query['more_like_this']['min_doc_freq'] = 1;
      $mlt_query['more_like_this']['min_term_freq'] = 1;
      $fields += array_values($query_options['mlt']['fields']);
      $body['query'] = $mlt_query;
    }

    // Build the query.
    if (!empty($query_options['query_search_string']) && !empty($query_options['query_search_filter'])) {
      $body['query']['filtered']['query'] = $query_options['query_search_string'];
      $body['query']['filtered']['filter'] = $query_options['query_search_filter'];
    }
    elseif (!empty($query_options['query_search_string'])) {
      if (empty($body['query'])) {
        $body['query'] = array();
      }
      $body['query'] += $query_options['query_search_string'];
    }
    elseif (!empty($query_options['query_search_filter'])) {
      $body['filter'] = $query_options['query_search_filter'];
    }

    // TODO: Handle fields on filter query.
    if (empty($fields)) {
      unset($body['fields']);
    }

    if (empty($body['filter'])) {
      unset($body['filter']);
    }

    if (empty($query_body)) {
      $query_body['match_all'] = array();
    }

    // Preserve the options for futher manipulation if necessary.
    $query->setOption('ElasticParams', $params);
    return $params;
  }

  /**
   * Helper function return associative array with query options.
   */
  protected function getSearchQueryOptions(SearchApiQueryInterface $query) {

    // Query options.
    $query_options = $query->getOptions();

    //Index fields
    $index_fields = $this->getIndexFields($query);

    // Range.
    $query_offset = empty($query_options['offset']) ? 0 : $query_options['offset'];
    $query_limit = empty($query_options['limit']) ? 10 : $query_options['limit'];

    // Query string.
    $query_search_string = NULL;

    // Query filter.
    $query_search_filter = NULL;

    // Full text search.
    $keys = $query->getKeys();
    if (!empty($keys)) {
      if (is_string($keys)) {
        $keys = array($keys);
      }

      // Full text fields in which to perform the search.
      $query_full_text_fields = $query->getFields();

      // Query string
      $search_string = $this->flattenKeys($keys, $query_options['parse mode']);

      if (!empty($search_string)) {
        $query_search_string = array('query_string' => array());
        $query_search_string['query_string']['query'] = $search_string;
        $query_search_string['query_string']['fields'] = array_values($query_full_text_fields);
        $query_search_string['query_string']['analyzer'] = 'snowball';
      }
    }

    // Sort.
    try {
      // TODO: Why we are calling SolrSearchQuery????
      $sort = $this->getSortSearchQuery($query);
    }
    catch (Exception $e) {
      watchdog('Elasticsearch Search API', check_plain($e->getMessage()), array(), WATCHDOG_ERROR);
      drupal_set_message($e->getMessage(), 'error');
    }

    // Filters.
    $parsed_query_filters = $this->parseFilter($query->getFilter(), $index_fields);
    if (!empty($parsed_query_filters)) {
      $query_search_filter = $parsed_query_filters[0];
    }

    // More Like This
    $mlt = array();
    if (isset($query_options['search_api_mlt'])) {
      $mlt = $query_options['search_api_mlt'];
    }

    return array(
      'query_offset' => $query_offset,
      'query_limit' => $query_limit,
      'query_search_string' => $query_search_string,
      'query_search_filter' => $query_search_filter,
      'sort' => $sort,
      'mlt' => $mlt,
    );
  }

  /**
   * Helper function that return Sort for query in search.
   */
  protected function getSortSearchQuery(SearchApiQueryInterface $query) {

    $index_fields = $this->getIndexFields($query);
    $sort = array();
    foreach ($query->getSort() as $field_id => $direction) {
      $direction = drupal_strtolower($direction);

      if ($field_id === 'search_api_relevance') {
        $sort['_score'] = $direction;
      }
      elseif (isset($index_fields[$field_id])) {
        $sort[$field_id] = $direction;
      }
      else {
        throw new Exception(t('Incorrect sorting!.'));
      }
    }
    return $sort;
  }

  /**
   * Helper function build facets in search.
   */
  protected function addSearchFacets(array &$params, SearchApiQueryInterface $query) {

    // SEARCH API FACETS.
    $facets = $query->getOption('search_api_facets');
    $index_fields = $this->getIndexFields($query);

    if (!empty($facets)) {
      // Loop trough facets.
      $elasticsearch_facets = array();
      foreach ($facets as $facet_id => $facet_info) {
        $field_id = $facet_info['field'];
        $facet = array($field_id => array());

        // Skip if not recognized as a known field.
        if (!isset($index_fields[$field_id])) {
          continue;
        }
        $field_type = search_api_extract_inner_type($index_fields[$field_id]['type']);

        // TODO: handle different types (GeoDistance and so on). See the supportedFeatures todo.
        if ($field_type === 'date') {
          $facet_type = 'date_histogram';
          $facet[$field_id] = $this->createDateFieldFacet($field_id, $facet);
        }
        else {
          $facet_type = 'terms';
          $facet[$field_id][$facet_type]['all_terms'] = (bool)$facet_info['missing'];
        }

        // Add the facet.
        if (!empty($facet[$field_id])) {
          // Add facet options
          $facet_info['facet_type'] = $facet_type;
          $facet[$field_id] = $this->addFacetOptions($facet[$field_id], $query, $facet_info);
        }
        $params['body']['facets'][$field_id] = $facet[$field_id];
      }
    }
  }

  /**
   * Helper function that add options and return facet
   */
  protected function addFacetOptions(&$facet, SearchApiQueryInterface $query, $facet_info) {
    $facet_limit = $this->getFacetLimit($facet_info);
    $facet_search_filter = $this->getFacetSearchFilter($query, $facet_info);

    // Set the field.
    $facet[$facet_info['facet_type']]['field'] = $facet_info['field'];

    // OR facet. We remove filters affecting the assiociated field.
    // TODO: distinguish between normal filters and facet filters.
    // See http://drupal.org/node/1390598.
    // Filter the facet.
    if (!empty($facet_search_filter)) {
       $facet['facet_filter'] = $facet_search_filter;
    }

    // Limit the number of returned entries.
    if ($facet_limit > 0 && $facet_info['facet_type'] == 'terms') {
      $facet[$facet_info['facet_type']]['size'] = $facet_limit;
    }

    return $facet;
  }

  /**
   * Helper function return Facet filter.
   */
  protected function getFacetSearchFilter(SearchApiQueryInterface $query, $facet_info ) {
    $index_fields = $this->getIndexFields($query);
    $facet_search_filter = '';

    if (isset($facet_info['operator']) && drupal_strtolower($facet_info['operator']) == 'or') {
      $facet_search_filter = $this->parseFilter($query->getFilter(), $index_fields, $facet_info['field']);
      if (!empty($facet_search_filter)) {
        $facet_search_filter = $facet_search_filter[0];
      }
    }
    // Normal facet, we just use the main query filters.
    else {
      $facet_search_filter = $this->parseFilter($query->getFilter(), $index_fields);
      if (!empty($facet_search_filter)) {
        $facet_search_filter = $facet_search_filter[0];
      }
    }

    return $facet_search_filter;
  }

  /**
   * Helper function create Facet for date field type.
   */
  protected function createDateFieldFacet($facet_id, $facet) {
    $result = $facet[$facet_id];

    $date_interval = $this->getDateFacetInterval($facet_id);
    $result['date_histogram']['interval'] = $date_interval;
    // TODO: Check the timezone cause this way of hardcoding doesn't seems right.
    $result['date_histogram']['time_zone'] = 'UTC';
    // Use factor 1000 as we store dates as seconds from epoch
    // not milliseconds.
    $result['date_histogram']['factor'] = 1000;

    return $result;
  }

  /**
   * Helper function that return facet limits
   */
  protected function getFacetLimit(array $facet_info) {
    // If no limit (-1) is selected, use the server facet limit option.
    $facet_limit = !empty($facet_info['limit']) ? $facet_info['limit'] : -1;
    if ($facet_limit < 0) {
      $facet_limit = $this->getOption('facet_limit', 10);
    }
    return $facet_limit;
  }

  /**
   * Helper function which add params to date facets.
   */
  protected function getDateFacetInterval($facet_id) {
    // Active search corresponding to this index.
    $searcher = key(facetapi_get_active_searchers());

    // Get the FacetApiAdpater for this searcher.
    $adapter = isset($searcher) ? facetapi_adapter_load($searcher) : NULL;

    // Get the date granularity.
    $date_gap = $this->getDateGranularity($adapter, $facet_id);

    switch ($date_gap) {
      // Already a selected YEAR, we want the months.
    	case 'YEAR':
    	  $date_interval = 'month';
    	  break;

    	  // Already a selected MONTH, we want the days.
    	case 'MONTH':
    	  $date_interval = 'day';
    	  break;

    	  // Already a selected DAY, we want the hours and so on.
    	case 'DAY':
    	  $date_interval = 'hour';
    	  break;

    	  // By default we return result counts by year.
    	default:
    	  $date_interval = 'year';
    }

    return $date_interval;
  }

  /**
   * Helper function to return date gap.
   */
  public function getDateGranularity($adapter, $facet_id) {
    // Date gaps.
    $gap_weight = array('YEAR' => 2, 'MONTH' => 1, 'DAY' => 0);
    $gaps = array();
    $date_gap = 'YEAR';

    // Get the date granularity.
    if (isset($adapter)) {
      // Get the current date gap from the active date filters.
      $active_items = $adapter->getActiveItems(array('name' => $facet_id));
      if (!empty($active_items)) {
        foreach ($active_items as $active_item) {
          $value = $active_item['value'];
          if (strpos($value, ' TO ') > 0) {
            list($date_min, $date_max) = explode(' TO ', str_replace(array('[', ']'), '', $value), 2);
            $gap = self::getDateGap($date_min, $date_max, FALSE);
            if (isset($gap_weight[$gap])) {
              $gaps[] = $gap_weight[$gap];
            }
          }
        }
        if (!empty($gaps)) {
          // Minimum gap.
          $date_gap = array_search(min($gaps), $gap_weight);
        }
      }
    }

    return $date_gap;
  }

  /**
   * Helper function which parse facets in search().
   */
  public function parseSearchResponse($response, SearchApiQueryInterface $query) {

    $search_result = array('results' => array());

    $search_result['result count'] = $response['hits']['total'];

    // Parse results.
    if (!empty($response['hits']['hits'])) {
      foreach ($response['hits']['hits'] as $result) {
        $id = $result['_id'];

        $search_result['results'][$id] = array(
          'id' => $result['_id'],
          'score' => $result['_score'],
          'fields' => $result['_source'],
        );
      }
    }

    $search_result['search_api_facets'] = $this->parseSearchFacets($response, $query);

    return $search_result;
  }

  /**
   *  Helper function that parse facets.
   */
  protected function parseSearchFacets($response, SearchApiQueryInterface $query) {

    $result = array();
    $index_fields = $this->getIndexFields($query);
    $facets = $query->getOption('search_api_facets');
    if (!empty($facets) && isset($response['facets'])) {
      foreach ($response['facets'] as $facet_id => $facet_data) {
        if (isset($facets[$facet_id])) {
          $facet_info = $facets[$facet_id];
          $facet_min_count = $facet_info['min_count'];

          $field_id = $facet_info['field'];
          $field_type = search_api_extract_inner_type($index_fields[$field_id]['type']);

          // TODO: handle different types (GeoDistance and so on).
          if ($field_type === 'date') {
            foreach ($facet_data['entries'] as $entry) {
              if ($entry['count'] >= $facet_min_count) {
                // Divide time by 1000 as we want seconds from epoch
                // not milliseconds.
                $result[$facet_id][] = array(
                  'count' => $entry['count'],
                  'filter' => '"' . ($entry['time'] / 1000) . '"',
                );
              }
            }
          }
          else {
            foreach ($facet_data['terms'] as $term) {
              if ($term['count'] >= $facet_min_count) {
                $result[$facet_id][] = array(
                  'count' => $term['count'],
                  'filter' => '"' . $term['term'] . '"',
                );
              }
            }
          }
        }
      }
    }

    return $result;
  }

  /**
   * Helper function. Get Autocomplete suggestions.
   *
   * @param SearchApiQueryInterface $query
   * @param SearchApiAutocompleteSearch $search
   * @param string $incomplete_key
   * @param string $user_input
   */
  public function getAutocompleteSuggestions(SearchApiQueryInterface $query, SearchApiAutocompleteSearch $search, $incomplete_key, $user_input) {
    $suggestions = array();
    // Turn inputs to lower case, otherwise we get case sensivity problems.
    $incomp = drupal_strtolower($incomplete_key);

    $index = $query->getIndex();
    $index_fields = $this->getIndexFields($query);

    $complete = $query->getOriginalKeys();
    $query->keys($user_input);

    try {
      // TODO: Making autocomplete to work as autocomplete instead of exact string match.
      $response = $this->search($query);
    }
    catch (Exception $e) {
      watchdog('Elasticsearch Search API', check_plain($e->getMessage()), array(), WATCHDOG_ERROR);
      return array();
    }

    $matches = array();
    if (isset($response['results'])) {
      $items = $index->loadItems(array_keys($response['results']));
      foreach ($items as $id => $item) {
        $node_title = $index->datasource()->getItemLabel($item);
        $matches[$node_title] = $node_title;
      }

      if ($matches) {
        // Eliminate suggestions that are too short or already in the query.
        foreach ($matches as $name => $node_title) {
          if (drupal_strlen($name) < 3 || isset($keys_array[$name])) {
            unset($matches[$name]);
          }
        }

        // The $count in this array is actually a score. We want the
        // highest ones first.
        arsort($matches);

        // Shorten the array to the right ones.
        $additional_matches = array_slice($matches, $limit - count($suggestions), NULL, TRUE);
        $matches = array_slice($matches, 0, $limit, TRUE);

        foreach ($matches as $node => $name) {
          $suggestions[] = $name;
        }
      }
      $keys = trim($keys . ' ' . $incomplete_key);
      return $suggestions;
    }
  }

  // TODO: Implement the settings update feature.

}
