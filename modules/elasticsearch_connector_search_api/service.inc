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

    if (module_exists('search_api_facetapi')) {
      // Facet settings.
      $form['facet_settings'] = array(
        '#type' => 'fieldset',
        '#title' => t('Elasticsearch facet settings'),
        '#tree' => FALSE,
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
    }

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
    $supported = drupal_map_assoc(array(
      'search_api_facets',
      'search_api_facets_operator_or',
      'search_api_autocomplete',
      'search_api_mlt',
      // TODO: Check for other features search_api should provide.
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
    // TODO: Implements.
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
}