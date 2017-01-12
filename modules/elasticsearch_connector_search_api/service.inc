<?php

/**
 * @file
 * Provides a Elasticsearch-based service class for the Search API using
 * Elasticsearch Connector module.
 */

class SearchApiElasticsearchConnectorException extends SearchApiException {}

/**
 * Dummy search service for when the ElasticSearch library isn't available.
 */
class SearchApiElasticsearchConnectorMissing extends SearchApiAbstractService {

  /**
   * {@inheritdoc}
   */
  public function indexItems(SearchApiIndex $index, array $items) {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteItems($ids = 'all', SearchApiIndex $index = NULL) {}

  /**
   * {@inheritdoc}
   */
  public function search(SearchApiQueryInterface $query) {
    return array();
  }

}

/**
 * Search service class.
 */
class SearchApiElasticsearchConnector extends SearchApiAbstractService {

  /**
   * Elasticsearch Connection.
   */
  protected $elasticsearchClient = NULL;
  private   $cluster_id = NULL;

  const FILTER_TYPE   = 'filter';
  const QUERY_TYPE    = 'query';

  const PREFIX_SEARCH = 'prefix_search';
  const PREFIX_SEARCH_FIELDS = 'prefix_search_fields';

  /**
   * Overrides __construct().
   */
  public function __construct(SearchApiServer $server) {
    parent::__construct($server);

    $this->cluster_id = $this->getCluster();
    if ($this->cluster_id) {
      $this->elasticsearchClient = elasticsearch_connector_get_client_by_id($this->cluster_id);
    }
  }

  /**
   * Get the Elasticsearch connector object.
   * @return object <\nodespark\DESConnector\ClientInterface, boolean, >
   */
  public function getConnectorObject() {
    return $this->elasticsearchClient;
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

    $clusters = array('' => t('Default cluster')) + elasticsearch_connector_cluster_load_all(TRUE);
    $form['connector_settings']['cluster'] = array(
      '#type' => 'select',
      '#title' => t('Cluster'),
      // This should not use the getCluster() method so that the service can be
      // configured to use the default cluster.
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
      'search_api_spellcheck',
      'search_api_data_type_location',
      // TODO: Implement the rest of the features available!
      // 'search_api_grouping',
      // 'search_api_multi',
      // 'search_api_service_extra',
      // 'search_api_data_type_geohash',
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
      '#markup' => '<div class="elasticsearch-daemon-status"><em>'
       . ((elasticsearch_connector_check_status($status)) ? 'running' : 'not running')
       . '</em>' . ' - <a href=" ' . url($elasticsearch_connector_path . '/clusters/' . $this->cluster_id . '/info') . ' ">'
       . t( 'More info' ) . '</a></div>',
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
        $client = $this->elasticsearchClient;
        if (!$client->indices()->exists(array('index' => $index_name))) {
          // TODO: This needs to be handled differently because the >2.0.0
          // needs index recreation!
          if (!empty($index->force_create)) {
            $params = array(
              'index' => $index_name,
              'body' => array(
                'settings' => array(
                  'number_of_shards' => $index->force_create['number_of_shards'],
                  'number_of_replicas' => $index->force_create['number_of_replicas'],
                )
              )
            );
            drupal_alter('elasticsearch_connector_search_api_add_index', $index, $params);
            $response = $client->indices()->create($params);
            if (!elasticsearch_connector_check_response_ack($response)) {
              drupal_set_message(t('The elasticsearch client wasn\'t able to create index'), 'error');
            }
          }
          else {
            throw new SearchApiElasticsearchConnectorException(t('The index you have selected, does not exist.'));
          }
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
      'id' => array('type' => 'string', 'include_in_all' => FALSE),
    );

    // Map index fields.
    foreach ($index->getFields() as $field_id => $field_data) {
      $properties[$field_id] = $this->getFieldMapping($field_data);
    }

    drupal_alter('elasticsearch_connector_search_api_field_update', $index, $properties);

    // Not remapping for read only indexes as it removes all data
    if (!isset($index->read_only) || !$index->read_only) {
      try {
        if ($this->elasticsearchClient->indices()->existsType($params)) {
          $current_mapping = $this->elasticsearchClient->indices()->getMapping($params);
          if (!empty($current_mapping)) {
            // If the mapping exits, delete it to be able to re-create it.
            $this->elasticsearchClient->indices()->deleteMapping($params);
          }
        }

        $params['body'][$params['type']]['properties'] = $properties;
        drupal_alter('elasticsearch_connector_search_api_mapping_update', $index, $params['body'][$params['type']]);

        $results = $this->elasticsearchClient->indices()->putMapping($params);
        if (!elasticsearch_connector_check_response_ack($results)) {
          drupal_set_message(t('Cannot create the mapping of the fields!'), 'error');
        }
      }
      catch (Exception $e) {
        drupal_set_message($e->getMessage(), 'error');
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Helper function to return the index param.
   * @param SearchApiIndex $index
   * @return array
   */
  public function getIndexParam(SearchApiIndex $index, $with_type = FALSE) {
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
    try {
      if ($this->versionIs2x()) {
        $params = $this->getIndexParam($index);
        if ($this->elasticsearchClient->indices()->exists($params)) {
          $this->elasticsearchClient->indices()->delete($params);
        }
      }
      else {
        $params = $this->getIndexParam($index, TRUE);

        // If there is no connection there is nothing we can do.
        if (!$this->elasticsearchClient) {
          return;
        }

        $this->elasticsearchClient->indices()->deleteMapping($params);

        // Check if there are any other types in the index before deleting it.
        $safe_delete = FALSE;
        $result = $this->elasticsearchClient->indices()->getMapping(array(
          'index' => $params['index'],
        ));

        // The 'get mapping' API response changed in version 1.4.0.beta1
        // @see http://www.elasticsearch.org/guide/en/elasticsearch/reference/1.4/indices-get-mapping.html
        $version = $this->getClusterVersion();
        if (version_compare($version, '1.4.0', '<')) {
          $safe_delete = empty($result[$params['index']]);
        }
        else {
          $safe_delete = empty($result[$params['index']]['mappings']);
        }

        if ($safe_delete) {
          $response = $this->elasticsearchClient->indices()->delete(array(
            'index' => $params['index'],
          ));
        }
      }
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
    if (!$elastic_type_exists) {
      $this->fieldsUpdated($index);
    }

    if (empty($items)) {
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

      $params['body'][] = array('index' => array('_id' => $this->getSafeId($data['id'])));
      $params['body'][] = $data;
    }

    // Let other modules alter params before sending them to Elasticsearch.
    drupal_alter('elasticsearch_connector_search_api_index_items', $index, $params, $items);

    try {
      $response = $this->elasticsearchClient->bulk($params);
      // If error throw the error we have.
      if (!empty($response['errors'])) {
        foreach($response['items'] as $item) {
          if (!empty($item['index']['status']) && $item['index']['status'] == '400') {
            watchdog('Elasticsearch Search API', $item['index']['error'], array(), WATCHDOG_ERROR);
          }
        }

        throw new SearchApiElasticsearchConnectorException(t('An error occurred during indexing. Check your watchdog for more information.'));
      }
    }
    catch (Exception $e) {
      throw new SearchApiElasticsearchConnectorException($e->getMessage(), NULL, $e);
    }

    return array_keys($items);
  }

  public function versionIs2x() {
    $version = $this->getClusterVersion();
    if (version_compare($version, '2.0', '>=')) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Get the index settings of an Elasticsearch index.
   * @param $index
   * @return mixed
   */
  public function getElasticsearchIndexSettings($index) {
    try {
      $indexName = $this->getIndexName($index);
      $params = $this->getIndexParam($index);
      $settings = $this->elasticsearchClient->indices()->getSettings($params);
      return $settings[$indexName]['settings'];
    }
    catch (Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
    }
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
      if ($this->versionIs2x()) {
        $settings = $this->getElasticsearchIndexSettings($index);
        $index->force_create['number_of_shards'] = $settings['index']['number_of_shards'];
        $index->force_create['number_of_replicas'] = $settings['index']['number_of_replicas'];
        $this->removeIndex($index);
        $this->addIndex($index);
      }
      else {
        $version = $this->getClusterVersion();
        $params = $this->getIndexParam($index, TRUE);
        $site_id = $this->getSiteHash();
        if ($site_id) {
          $params['body'] = array('query' => array('match' => array('search_api_site_hash' => $site_id)));
        }
        else {
          $params['body'] = array('query' => array('match_all' => array()));
        }

        // The 'delete by query' API request changed in version 1.0.0RC1.
        // @see http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/docs-delete-by-query.html
        if (version_compare($version, '1.0.0', '<')) {
          $params['body'] = $params['body']['query'];
        }

        $this->elasticsearchClient->deleteByQuery($params);
      }
    }
    else {
      $this->deleteItemsIds($ids, $index);
    }
  }

  /**
   * Get all the indexes for this specific server.
   */
  protected function getIndexes() {
    $server_indices = array();
    $indices = search_api_index_load_multiple(FALSE);
    foreach ($indices as $index) {
      if ($index->server == $this->server->machine_name) {
        $server_indices[] = $index;
      }
    }
    return $server_indices;
  }

  /**
   * Helper function for bulk delete operation.
   *
   * @param array $ids
   * @param SearchApiIndex $index
   * @return void
   */
  private function deleteItemsIds($ids, SearchApiIndex $index) {
    $params = $this->getIndexParam($index, TRUE);
    foreach ($ids as $id) {
      $params['body'][] = array(
        'delete' => array(
          '_type'   => $params['type'],
          '_id'     => $this->getSafeId($id),
        )
      );
    }

    try {
      $response = $this->elasticsearchClient->bulk($params);
      // If error throw the error we have.
      if (!empty($response['errors'])) {
        foreach($response['items'] as $item) {
          if (!empty($item['delete']['status']) && $item['delete']['status'] == '400') {
            throw new SearchApiElasticsearchConnectorException($item['index']['error']);
          }
        }
      }
    }
    catch (Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
    }
  }


  /**
   *
   */
  protected function checkClient($throwError = FALSE) {
    if (empty($this->elasticsearchClient) || !($this->elasticsearchClient instanceof \nodespark\DESConnector\ClientInterface)) {
      if ($throwError) {
        throw new SearchApiElasticsearchConnectorException(t('Elasticsearch library hasn\'t been initialized successfully.' ));
      }
      else {
        return FALSE;
      }
    }
    else {
      return TRUE;
    }
  }

  /**
   * Overrides search().
   */
  public function search(SearchApiQueryInterface $query, $suggestions = FALSE) {
    // Results.
    $search_result = array('result count' => 0);

    // Get index
    $index = $query->getIndex();

    $params = $this->getIndexParam($index, TRUE);

    // Check elasticsearch index.
    if (!$this->checkClient()) {
      return $search_result;
    }

    $query->setOption('ElasticParams', $params);

    // Build Elastica query.
    $params = $this->buildSearchQuery($query);

    // Add facets.
    $this->addSearchFacets($params, $query);

    $this->buildAdditionalProcessorQuery($params, $query);

    // Alter the query and params.
    drupal_alter('elasticsearch_connector_search_api_query', $query, $params);

    try {
      // Do search.
      $response = $this->elasticsearchClient->search($params);

      // TODO: Fix the logging to be accurate!
      if (!empty($index->options['collect_index_statistics']) && !$suggestions
          && class_exists('SearchApiElasticsearchConnectorStats')) {
        $stats = new SearchApiElasticsearchConnectorStats($query, $this);
        $stats->logStat($response);
      }

      // Parse response.
      $results = $this->parseSearchResponse($response, $query);
      drupal_alter('elasticsearch_connector_search_api_results', $results, $query, $response);
    }
    catch (Exception $e) {
      watchdog('Elasticsearch Search API', check_plain($e->getMessage()), array(), WATCHDOG_ERROR);
      return array('result count' => 0);
    }

    return $results;
  }

  /**
   * Adding all additional parameters to the search comming from processors.
   * @param array $params
   * @param SearchApiQueryInterface $query
   */
  protected function buildAdditionalProcessorQuery(&$params, SearchApiQueryInterface $query) {
    $elasticsearch_processors_params = $query->getOption('elasticsearch_processors_params', array());
    if (!empty($elasticsearch_processors_params)) {
      foreach ($elasticsearch_processors_params as $body_key => $body_value) {
        $params['body'][$body_key] = $body_value;
      }
    }
  }

  /**
   * Handle the spellcheck
   * @param SearchApiQueryInterface $query
   */
  protected function buildSpellcheckQuery(SearchApiQueryInterface $query, &$params) {
    $options = $query->getOptions();
    $keys = $query->getOriginalKeys();
    if (!empty($options['search_api_spellcheck']) && !empty($keys)) {
      $fields = $query->getFields();
      if (!empty($fields)) {
        $params['body']['suggest'] = array(
          'text' => $query->getOriginalKeys(),
        );

        foreach ($fields as $field) {
          $params['body']['suggest'][$field . '_spellcheck'] = array(
            'phrase' => array(
              'field' =>$field
            )
          );
        }
      }
    }
  }

  /**
   * Recursively parse Search API filters.
   */
  protected function parseFilter(SearchApiQueryFilterInterface $query_filter, $index_fields, $ignored_field_id = '') {

    if (empty($query_filter)) {
      return NULL;
    }
    else {
      $conjunction = $query_filter->getConjunction();

      $filters = array();
      $queries = array();

      try {
        foreach ($query_filter->getFilters() as $filter_info) {
          $filter = NULL;
          $filter_type = NULL;

          // Simple filter [field_id, value, operator].
          if (is_array($filter_info)) {
            $filter_assoc = $this->getAssociativeFilter($filter_info);
            $this->correctFilter($filter_assoc, $index_fields, $ignored_field_id);
            // Check if we need to ignore the filter!
            if ($filter_assoc['field_id'] != $ignored_field_id) {
              list($filter_type, $filter) = $this->getFilter($filter_assoc, $index_fields);
            }

            if (!empty($filter)) {
              if ($filter_type == self::FILTER_TYPE) {
                $filters[] = $filter;
              }
              elseif ($filter_type == self::QUERY_TYPE) {
                $queries[] = $filter;
              }
            }
          }
          // Nested filters.
          elseif ($filter_info instanceof SearchApiQueryFilterInterface) {
            list($nested_queries, $nested_filters) = $this->parseFilter($filter_info, $index_fields, $ignored_field_id);
            // TODO: handle error. - here is unnecessary cause in if we thow exceptions and this is still in try{}  .
            if (!empty($nested_filters)) {
              $filters = array_merge($filters, $nested_filters);
            }
            if (!empty($nested_queries)) {
              $queries = array_merge($queries, $nested_queries);
            }
          }
        }

        if (!empty($queries)) {
          $queries = $this->normalizeFulltextQuery($queries, $conjunction);
        }

        if (!empty($filters)) {
          $filters = $this->setFiltersConjunction($filters, $conjunction);
        }
      }
      catch (Exception $e) {
        watchdog('Elasticsearch Search API', check_plain($e->getMessage()), array(), WATCHDOG_ERROR);
        drupal_set_message(check_plain($e->getMessage()), 'error');
      }

      return array($queries, $filters);
    }
  }

  /**
   *
   * @param array $queries
   * @param string $conjunction
   */
  protected function normalizeFulltextQuery($queries, $conjunction) {
    if ($conjunction === 'OR') {
      $queries = array(array('bool' => array('should' => $queries, 'minimum_should_match' => '1')));
    }
    elseif ($conjunction === 'AND') {
      $queries = array(array('bool' => array('must' => $queries)));
    }

    return $queries;
  }

  /**
   * Get filter by associative array.
   */
  protected function getFilter(array $filter_assoc, $index_fields) {
    $field_type = $index_fields[$filter_assoc['field_id']]['type'];
    $type = self::FILTER_TYPE;
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
    elseif (!search_api_is_text_type($field_type)) {
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

        case '*':
          $type = self::QUERY_TYPE;
          $filter = array(
            'wildcard' => array($filter_assoc['field_id'] => $filter_assoc['filter_value'])
          );
          break;

        default:
          throw new Exception(t('Undefined operator :field_operator for :field_id field! Incorrect filter criteria is using for searching!',
          array(':field_operator' => $filter_assoc['filter_operator'], ':field_id' => $filter_assoc['field_id'])));
          break;
      }
    }
    else {
      // Handle the freetext search filters when the $query->condition(...) has been used with a fulltext field.
      $type = self::QUERY_TYPE;
      switch ($filter_assoc['filter_operator']) {
        case '=':
          $filter = array(
            'match' => array($filter_assoc['field_id'] => array('query' => $filter_assoc['filter_value'], 'operator' => 'and'))
          );
          break;

          case '*':
            $filter = array(
              'wildcard' => array($filter_assoc['field_id'] => $filter_assoc['filter_value'])
            );
            break;

        default:
          throw new Exception(t('Undefined operator :field_operator for :field_id field! Incorrect filter criteria is using for searching!',
          array(':field_operator' => $filter_assoc['filter_operator'], ':field_id' => $filter_assoc['field_id'])));
          break;
      }
    }

    return array($type, $filter);
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
   * Helper function that set filters conjunction
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
   * Get the configured cluster; if the cluster is blank, use the default.
   */
  public function getCluster() {
    $cluster_id = $this->getOption('cluster', '');
    return empty($cluster_id) ? elasticsearch_connector_get_default_connector() : $cluster_id;
  }

  /**
   * Helper function. Returns the elasticsearch name of an index.
   */
  public function getIndexName(SearchApiIndex $index) {
    global $databases;
    $site_database = $databases['default']['default']['database'];
    return !empty($index->options['index_name']['index']) ? $index->options['index_name']['index'] : $site_database;
  }

  /**
   * Helper function. Get the elasticsearch mapping for a field.
   */
  public function getFieldMapping($field) {
    // Support of the custom data types. When such is implemented the type is
    // stored in the $field['real_type'] and the $field['type'] contains the
    // fallback type that will be used if the custom one is not supported.
    $field_type = (isset($field['real_type'])) ? $field['real_type'] : $field['type'];
    $type = search_api_extract_inner_type($field_type);

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
          'format' => 'epoch_second',
        );

      case 'location':
        return array(
          'type' => 'geo_point',
          'lat_lon'=> TRUE,
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
   *
   */
  protected function getItemFromElasticsearch(SearchApiIndex $index, $id) {
    $params = $this->getIndexParam($index, TRUE);
    $params['id'] = $id;
    try {
      $response = $this->elasticsearchClient->get($params);
      return $response;
    }
    catch (Exception $e) {
      watchdog('Elasticsearch Search API', check_plain($e->getMessage()), array(), WATCHDOG_ERROR);
    }
  }

  /**
   * Get the Elasticsearch server version.
   * @return string
   */
  protected function getClusterVersion() {
    static $version;

    if (!isset($version)) {
      try {
        $info = $this->elasticsearchClient->info();
        $version = $info['version']['number'];
      }
      catch (Exception $e) {
        watchdog('Elasticsearch Search API', check_plain($e->getMessage()), array(), WATCHDOG_ERROR);
      }
    }

    return $version;
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

    // Handle spellcheck if enabled
    $this->buildSpellcheckQuery($query, $params);

    /**
     * $query_options['spatials']:
     * - field: The Search API field identifier of the location field. Must be indexed
     *   as type "location".
     * - lat: The latitude of the point on which this location parameter is centered.
     * - lon: The longitude of that point.
     * - radius: (optional) If results should be filtered according to their distance
     *   to the point, the maximum distance at which a point should be included (in
     *   kilometers).
     * - method: (optional) The method to use for filtering. This is backend-specific
     *   and not guaranteed to have an effect. Service classes should ignore values of
     *   this option which they don't recognize.
     */
    // Search API Location support.
    if (!empty($query_options['spatials'])) {
      foreach ($query_options['spatials'] as $i => $spatial) {
        if (empty($spatial['field']) || empty($spatial['lat']) || empty($spatial['lon'])) {
          continue;
        }

        // Shortcut to easily reuse the field and point.
        $field = $spatial['field'];
        $point = array(
          'lat' => (float) $spatial['lat'],
          'lon' => (float) $spatial['lon'],
        );

        // Prepare the filter settings.
        if (isset($spatial['radius'])) {
          $radius = (float) $spatial['radius'];
        }

        // TODO: Implement the other geo filter types.
        // TODO: Implement the functionality to have multiple filters together
        // with the geo filters.

        // // Geo bounding box filter.
        // $query_options['query_search_filter'] = array(
        //   'geo_bounding_box' => array(
        //     $spatial['field'] => array(
        //       'top_left' => array(
        //         'lat' => '',
        //         'lon' => '',
        //       ),
        //       'bottom_right' => array(
        //         'lat' => '',
        //         'lon' => '',
        //       ),
        //     ),
        //   ),
        // );

        // Geo Distance filter.
        $geo_distance_filter = array(
          'geo_distance' => array(
            'distance' => $radius . 'km',
            $spatial['field'] => $point,
          ),
        );

        if (!empty($query_options['query_search_filter'])) {
          $query_options['query_search_filter'] = array(
            'and' => array(
              $query_options['query_search_filter'],
              $geo_distance_filter
            ),
          );
        } else {
          $query_options['query_search_filter'] = $geo_distance_filter;
        }

        // // Geo distance range filter.
        // $query_options['query_search_filter'] = array(
        //   'geo_distance_range' => array(
        //     'from' => '',
        //     'to' => '',
        //     $spatial['field'] => $point,
        //   ),
        // );

        // // Geo polygon filter.
        // $query_options['query_search_filter'] = array(
        //   'geo_polygon' => array(
        //     $spatial['field'] => array(
        //       'points' => array(),
        //     ),
        //   ),
        // );

        // // Geoshape filter.
        // $query_options['query_search_filter'] = array(
        //   'geo_shape' => array(
        //     $spatial['field'] => array(
        //       'shape' => array(
        //         'type' => 'envelope',
        //         'coordinates' => array(),
        //       ),
        //     ),
        //   ),
        // );

        // // Geohash cell filter.
        // $query_options['query_search_filter'] = array(
        //   'geohash_cell' => array(
        //     $spatial['field'] => $point,
        //     'precision' => '',
        //     'neighbors' => '',
        //   ),
        // );
      }
    }

    // Build the query.
    if (!empty($query_options['query_search']) && !empty($query_options['query_search_filter'])) {
      $body['query']['filtered']['query'] = $query_options['query_search'];
      $body['query']['filtered']['filter'] = $query_options['query_search_filter'];
    }
    elseif (!empty($query_options['query_search'])) {
      if (empty($body['query'])) {
        $body['query'] = array();
      }
      $body['query'] += $query_options['query_search'];
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
   * Helper function. Handle wildcard query.
   * @param SearchApiQueryInterface $query
   * @return array
   *   Return the should cuase for the Elasticsearch query.
   */
  protected function handleWildcardQuery(SearchApiQueryInterface $query, $index_fields) {
    $originalKeys = $query->getOriginalKeys();

    $wildcard_query = array();
    if (!empty($originalKeys) && preg_match('/\*|\?/', $originalKeys)) {
      $query_full_text_fields = $query->getFields();
      foreach ($query_full_text_fields as $fulltext_field) {
        $boost = isset($index_fields[$fulltext_field]['boost']) ? '^' . $index_fields[$fulltext_field]['boost'] : '';
        $field_values = array('value' => strtolower($originalKeys));
        if (isset($index_fields[$fulltext_field]['boost'])) {
          $field_values['boost'] = $index_fields[$fulltext_field]['boost'];
        }

        $wildcard_query[] = array(
          'wildcard' => array(
            $fulltext_field => $field_values
          )
        );
      }
    }

    return $wildcard_query;
  }

  /**
   * @param SearchApiQueryInterface $query
   * @return array
   */
  private function handlePrefixQueryFields(SearchApiQueryInterface $query) {
    $query_full_text_fields = $query->getFields();
    $fields = $query->getOption(self::PREFIX_SEARCH_FIELDS, array());
    if (empty($fields)) {
      $fields = $query_full_text_fields;
    }
    else {
      $fields = array_keys($fields);
    }

    return $fields;
  }

  /**
   *
   * @param SearchApiQueryInterface $query
   * @param array $index_fields
   * @return array
   *  The query to be used in final query structure.
   */
  protected function handlePrefixQuery(SearchApiQueryInterface $query, $index_fields) {
    $originalKeys = $query->getOriginalKeys();
    $prefix_query = array();
    if (!empty($originalKeys)) {
      $query_full_text_fields = $this->handlePrefixQueryFields($query);
      foreach ($query_full_text_fields as $fulltext_field) {
        $boost = isset($index_fields[$fulltext_field]['boost']) ? '^' . $index_fields[$fulltext_field]['boost'] : '';
        $field_values = array('prefix' => strtolower($originalKeys));
        if (isset($index_fields[$fulltext_field]['boost'])) {
          $field_values['boost'] = $index_fields[$fulltext_field]['boost'];
        }

        $prefix_query[] = array(
          'prefix' => array(
            $fulltext_field => $field_values
          )
        );
      }
    }
    return $prefix_query;
  }

  /**
   * Handle the multy match query.
   *
   * @param SearchApiQueryInterface $query
   * @param array $index_fields
   * @return array
   *  The query to be used in final query structure.
   */
  protected function handleMultyMatchQuery(SearchApiQueryInterface $query, $index_fields) {
    $originalKeys = $query->getOriginalKeys();

    $multymatch_query = array();
    if (!empty($originalKeys)) {
      $query_full_text_fields = $query->getFields();
      $fields = array();
      foreach ($query_full_text_fields as $fulltext_field) {
        $boost = isset($index_fields[$fulltext_field]['boost']) ? '^' . $index_fields[$fulltext_field]['boost'] : '';
        $fields[] = $fulltext_field . $boost;
      }

      $multymatch_query = array(
        'multi_match' => array(
          'query' => $originalKeys,
          'fields' => $fields
        )
      );
    }

    return $multymatch_query;
  }

  /**
   * Check if the query has a wildcard parameter or not.
   *
   * @param SearchApiQueryInterface $query
   * @return boolean
   */
  protected function isWildcardQuery(SearchApiQueryInterface $query) {
    $return = FALSE;
    $originalKeys = $query->getOriginalKeys();

    if (!empty($originalKeys) && preg_match('/\*|\?/', $originalKeys)) {
      $return = TRUE;
    }
    return $return;
  }

  /**
   * Build the string_query for the Elasticsearch request.
   * @param SearchApiQueryInterface $query
   * @param array $index_fields
   * @return array
   */
  protected function handleStringQuery(SearchApiQueryInterface $query, $index_fields) {
    $query_search_string = array();
    if (!$this->isWildcardQuery($query)) {
      // Get query options.
      $query_options = $query->getOptions();

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

          foreach ($query_full_text_fields as $fulltext_field) {
            $boost = isset($index_fields[$fulltext_field]['boost']) ? '^' . $index_fields[$fulltext_field]['boost'] : '';
            $query_search_string['query_string']['fields'][] = $fulltext_field . $boost;
          }
        }
      }
    }

    return $query_search_string;
  }

  /**
   * Helper function. Handle freetext search parameters.
   *
   * @param SearchApiQueryInterface $query
   * @return array
   *   Return the query parameters required for elasticsearch.
   */
  protected function handleFulltextSearch(SearchApiQueryInterface $query, $index_fields) {
    $bool_query = array();

    $wildcardQuery = $this->handleWildcardQuery($query, $index_fields);
    if (!empty($wildcardQuery)) {
      $bool_query['bool']['should'][] = $wildcardQuery;
    }

    $multyMatch = $this->handleMultyMatchQuery($query, $index_fields);
    // If prefix enabled execute the prefix query.
    $prefixMatch = array();
    if ($query->getOption(self::PREFIX_SEARCH, FALSE) && !$this->isWildcardQuery($query)) {
      $prefixMatch = $this->handlePrefixQuery($query, $index_fields);
    }

    if (!empty($multyMatch) && $this->isWildcardQuery($query)) {
      $bool_query['bool']['should'][] = $multyMatch;
    }
    elseif (!empty($multyMatch) && !$this->isWildcardQuery($query) && !empty($prefixMatch)) {
      $bool_query['bool']['should'][] = $prefixMatch;
      $bool_query['bool']['should'][] = $multyMatch;
    }
    elseif (!empty($multyMatch) && !$this->isWildcardQuery($query)) {
      $bool_query['bool']['must'][] = $multyMatch;
    }

    $mlt_query = $this->handleMLTSearch($query, $index_fields);
    //var_dump($mlt_query);exit;
    if (!empty($mlt_query)) {
      $bool_query['bool']['should'][] = $mlt_query;
    }

    $stringQuery = $this->handleStringQuery($query, $index_fields);
    if (!empty($stringQuery)) {
      $bool_query['bool']['should'][] = $stringQuery;
    }

    return $bool_query;
  }

  /**
   * Handle the "More like this" functionality if it is required.
   *
   * @param SearchApiQueryInterface $query
   * @param array $index_fields
   * @return array
   */
  protected function handleMLTSearch(SearchApiQueryInterface $query, $index_fields) {
    $query_options = $query->getOptions();
    $mlt_queries = array();
    // More Like This
    if (!empty($query_options['search_api_mlt'])) {
      $mlt = $query_options['search_api_mlt'];
      $mlt_query['more_like_this'] = array();
      $version = $this->getClusterVersion();
      if (version_compare($version, '1.3.0', '>=')) {
        $mlt_query['more_like_this']['ids'] = array($mlt['id']);
        $mlt_query['more_like_this']['fields'] = array_values($mlt['fields']);
        $mlt_query['more_like_this']['max_query_terms']   = $mlt['max_query_terms'];
        $mlt_query['more_like_this']['min_doc_freq']      = $mlt['min_doc_freq'];
        $mlt_query['more_like_this']['min_term_freq']     = $mlt['min_term_freq'];

        $mlt_queries[] = $mlt_query;
      }
      else {
        $document = $this->getItemFromElasticsearch($query->getIndex(), $mlt['id']);
        $fields = array_values($mlt['fields']);
        foreach ($fields as $field) {
          $mlt_query = array();
          $mlt_query['more_like_this']['fields']            = array($field);
          $mlt_query['more_like_this']['max_query_terms']   = $mlt['max_query_terms'];
          $mlt_query['more_like_this']['min_doc_freq']      = $mlt['min_doc_freq'];
          $mlt_query['more_like_this']['min_term_freq']     = $mlt['min_term_freq'];
          $mlt_query['more_like_this']['like_text']         = $document['_source'][$field];

          $mlt_queries[] = $mlt_query;
        }
      }
    }

    return $mlt_queries;
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
    $query_search = $this->handleFulltextSearch($query, $index_fields);

    // Query filter.
    $query_search_filter = NULL;

    // Sort.
    try {
      $sort = $this->getSortSearchQuery($query);
    }
    catch (Exception $e) {
      watchdog('Elasticsearch Search API', check_plain($e->getMessage()), array(), WATCHDOG_ERROR);
      drupal_set_message($e->getMessage(), 'error');
    }

    // Filters.
    list($parsed_queries, $parsed_query_filters) = $this->parseFilter($query->getFilter(), $index_fields);
    if (!empty($parsed_query_filters)) {
      $query_search_filter = $parsed_query_filters[0];
    }

    if (!empty($parsed_queries)) {
      $parsed_queries = reset($parsed_queries);
      if (empty($query_search)) {
        $query_search = $parsed_queries;
      }
      else {
        $query_search = array('bool' => array('must' => array($query_search, $parsed_queries)));
      }
    }

    // More Like This
    $mlt = array();
    if (isset($query_options['search_api_mlt'])) {
      $mlt = $query_options['search_api_mlt'];
    }

    // Handle spatial filters.
    $spatial = array();
    if (isset($query_options['search_api_location'])) {
      $spatial = $query_options['search_api_location'];
    }

    return array(
      'query_offset' => $query_offset,
      'query_limit' => $query_limit,
      'query_search' => $query_search,
      'query_search_filter' => $query_search_filter,
      'sort' => $sort,
      'spatials' => $spatial,
    );
  }

  /**
   * Helper function that return Sort for query in search.
   */
  protected function getSortSearchQuery(SearchApiQueryInterface $query) {

    $index_fields = $this->getIndexFields($query);
    $sort = array();

    // Extract Search API Location fields.
    $search_api_location = $query->getOption('search_api_location');
    $spatials = array();
    if ($search_api_location) {
      foreach ($search_api_location as $spatial) {
        $spatials[$spatial['field']] = $spatial;
      }
    }

    foreach ($query->getSort() as $field_id => $direction) {
      $direction = drupal_strtolower($direction);

      if ($field_id === 'search_api_relevance') {
        $sort['_score'] = $direction;
      }
      elseif (isset($index_fields[$field_id])) {
        // Check whether the field is a location field.
        if (isset($index_fields[$field_id]['real_type']) && $index_fields[$field_id]['real_type'] == 'location') {
          // Only set proximity sort if the point is set.
          if (isset($spatials[$field_id])) {
            $sort['_geo_distance'] = array(
              $field_id => array(
                'lat' => $spatials[$field_id]['lat'],
                'lon' => $spatials[$field_id]['lon'],
              ),
              'order' => $direction,
              'unit' => 'km',
              'distance_type' => 'plane',
            );
          }
        }
        else {
          $sort[$field_id] = $direction;
        }
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
      $aggs = \nodespark\DESConnector\Elasticsearch\Aggregations\Aggregations::getInstance();
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
          $interval = $this->getDateFacetInterval($facet_id);
          $facet[$field_id] = new \nodespark\DESConnector\Elasticsearch\Aggregations\Bucket\DateHistogram($field_id, $field_id, $interval);
          $aggs->setAggregation($facet[$field_id]);
        }
        else {
          $facet[$field_id] = new \nodespark\DESConnector\Elasticsearch\Aggregations\Bucket\Terms($field_id, $field_id);
          $facet[$field_id]->setSize($this->getFacetLimit($facet_info));
          $aggs->setAggregation($facet[$field_id]);
        }
      }

      $aggs->applyAggregationsToParams($params);
    }
  }

  /**
   * Helper function return Facet filter.
   */
  protected function getFacetSearchFilter(SearchApiQueryInterface $query, $facet_info ) {
    $index_fields = $this->getIndexFields($query);
    $facet_search_filter = '';

    if (isset($facet_info['operator']) && drupal_strtolower($facet_info['operator']) == 'or') {
      list($queries, $facet_search_filter) = $this->parseFilter($query->getFilter(), $index_fields, $facet_info['field']);
      if (!empty($facet_search_filter)) {
        $facet_search_filter = $facet_search_filter[0];
      }
    }
    // Normal facet, we just use the main query filters.
    else {
      list($queries, $facet_search_filter) = $this->parseFilter($query->getFilter(), $index_fields);
      if (!empty($facet_search_filter)) {
        $facet_search_filter = $facet_search_filter[0];
      }
    }

    return $facet_search_filter;
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
          'fields' => isset($result['_source']) ? $result['_source'] : array(),
        );

        foreach ($result as $key => $value) {
          if (!in_array($key, array('_id', '_score', '_source'))) {
            $search_result['results'][$id][$key] = $value;
          }
        }
      }
    }

    $search_result['search_api_facets'] = $this->parseSearchFacets($response, $query);

    // Check for spellcheck suggestions.
    if (module_exists('search_api_spellcheck') && $query->getOption('search_api_spellcheck')) {
      $search_result['search_api_spellcheck'] = new SearchApiSpellcheckElasticsearch($response);
    }

    return $search_result;
  }

  /**
   *  Helper function that parse facets.
   */
  protected function parseSearchFacets($response, SearchApiQueryInterface $query) {

    $result = array();
    $index_fields = $this->getIndexFields($query);
    $facets = $query->getOption('search_api_facets');
    if (!empty($facets) && isset($response['aggregations'])) {
      foreach ($response['aggregations'] as $facet_id => $facet_data) {
        if (isset($facets[$facet_id])) {
          $facet_info = $facets[$facet_id];
          $facet_min_count = $facet_info['min_count'];

          $field_id = $facet_info['field'];
          $field_type = search_api_extract_inner_type($index_fields[$field_id]['type']);

          // TODO: handle different types (GeoDistance and so on).
          if ($field_type === 'date') {
            foreach ($facet_data['buckets'] as $entry) {
              if ($entry['doc_count'] >= $facet_min_count) {
                // Divide time by 1000 as we want seconds from epoch
                // not milliseconds.
                $result[$facet_id][] = array(
                  'count' => $entry['doc_count'],
                  'filter' => '"' . ($entry['key']/1000) . '"',
                );
              }
            }
          }
          else {
            foreach ($facet_data['buckets'] as $term) {
              if ($term['doc_count'] >= $facet_min_count) {
                if ($field_type == 'boolean') {
                  $term_value = ($term['key'] == 'T' ? TRUE : FALSE);
                }
                else {
                  $term_value = '"' . $term['key'] . '"';
                }

                $result[$facet_id][] = array(
                  'count' => $term['doc_count'],
                  'filter' => $term_value,
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
   * Implements SearchApiAutocompleteInterface::getAutocompleteSuggestions().
   */
  public function getAutocompleteSuggestions(SearchApiQueryInterface $query, SearchApiAutocompleteSearch $search, $incomplete_key, $user_input) {
    $suggestions = array();
    // Turn inputs to lower case, otherwise we get case sensivity problems.
    $incomp = drupal_strtolower($incomplete_key);

    $index = $query->getIndex();
    $complete = $query->getOriginalKeys();
    $query->keys($user_input);

    try {
      $query->setOption(self::PREFIX_SEARCH, TRUE);
      $query->setOption(self::PREFIX_SEARCH_FIELDS, array_flip($query->getFields()));
      $response = $this->search($query, TRUE);
      // Postprocess the search results.
      $query->postExecute($response);
    }
    catch (Exception $e) {
      watchdog('Elasticsearch Search API', check_plain($e->getMessage()), array(), WATCHDOG_ERROR);
      return array();
    }

    $matches = array();
    if (isset($response['results'])) {
      $label_field = $index->datasource()->getMetadataWrapper()->entityKey('label');
      $items = $index->loadItems(array_keys($response['results']));
      foreach ($items as $id => $item) {
        $item_title = isset($response['results'][$id]['fields'][$label_field]) ? $response['results'][$id]['fields'][$label_field] : $index->datasource()->getItemLabel($item);
        $url_item = $index->datasource()->getItemUrl($item);
        if (!empty($search->options['custom']['link_suggestions'])) {
          $matches[$item_title] = array(
            'key' => $index->datasource()->getItemLabel($item),
            'user_input' => '',
            'render' => l($item_title, $url_item['path'], array('html' => TRUE)),
          );
        }
        else {
          $matches[$item_title] = array(
            'key' => $index->datasource()->getItemLabel($item),
            'user_input' => '',
            'render' => $item_title,
          );
        }
      }

      return $matches;
    }
  }

  /**
   * Provides support for search_api_site hashes to add to IDs.
   * See https://www.drupal.org/files/1776534.patch for Solr version.
   */
  public function getSiteHash() {
    if (function_exists('search_api_site_hash')) {
      return search_api_site_hash();
    }
    return FALSE;
  }

  /**
   * Create document ID using site hash, if available.
   */
  public function getSafeId($id) {
    $site_id = $this->getSiteHash();
    return $site_id ? "{$site_id}-{$id}" : $id;
  }

}
