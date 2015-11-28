<?php

/**
 * @file
 * Contains the SearchApiElasticsearchBackend object.
 *
 * TODO: Check for dependencies and remove them in order to properly test the code.
 */

namespace Drupal\elasticsearch_connector\Plugin\search_api\backend;

use Drupal\search_api\SearchApiException;
use Drupal\Component\Utility\String;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Config\Config;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\search_api\Backend\BackendPluginBase;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\FilterInterface;
use Drupal\search_api\Utility as SearchApiUtility;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\elasticsearch_connector\Entity\Cluster;
use Drupal\search_api\Item\FieldInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;

/**
 * @SearchApiBackend(
 *   id = "elasticsearch",
 *   label = @Translation("Elasticsearch"),
 *   description = @Translation("Index items using an Elasticsearch server.")
 * )
 */
class SearchApiElasticsearchBackend extends BackendPluginBase {

  protected $elasticsearchSettings = NULL;
  protected $clusterId = NULL;

  /** @var Cluster $clusterEntity  */
  protected $clusterEntity;

  /** @var DESConnector $elasticsearchClient  */
  protected $elasticsearchClient = NULL;

  public function __construct(array $configuration, $plugin_id, array $plugin_definition, FormBuilderInterface $form_builder, ModuleHandlerInterface $module_handler, Config $elasticsearch_settings) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->formBuilder = $form_builder;
    $this->moduleHandler = $module_handler;
    $this->elasticsearchSettings = $elasticsearch_settings;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder'),
      $container->get('module_handler'),
      $container->get('config.factory')->get('elasticsearch.settings')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array(
      'cluster_settings' => array (
          'cluster' => ''
        ),
      'scheme' => 'http',
      'host' => 'localhost',
      'port' => '9200',
      'path' => '',
      'excerpt' => FALSE,
      'retrieve_data' => FALSE,
      'highlight_data' => FALSE,
      'http_method' => 'AUTO',
      'autocorrect_spell' => TRUE,
      'autocorrect_suggest_words' => TRUE,
    );
  }

  /**
   * Overrides configurationForm().
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    if (!$this->server->isNew()) {
      $serverlink = $this->getServerLink();
      // Editing this server
      $form['server_description'] = array(
        '#type' => 'item',
        '#title' => $this->t('Elasticsearch Cluster'),
        '#description' => \Drupal::l($serverlink, Url::fromUri($serverlink)),
      );
    }
    $form['cluster_settings'] = array(
      '#type' => 'fieldset',
      '#title' => t('Elasticsearch settings'),
      '#tree' => FALSE,
    );

    //We are not displaying disabled clusters
    $clusters = Cluster::loadAllClusters(FALSE);
    $options = array();
    foreach ($clusters as $key => $cluster) {
      $options[$key] = $cluster->cluster_id;
    }
    $options[Cluster::getDefaultCluster()] = t('Default cluster: ' . Cluster::getDefaultCluster());
    $form['cluster_settings']['cluster'] = array(
      '#type' => 'select',
      '#title' => t('Cluster'),
      '#required' => TRUE,
      '#options' => $options,
      '#default_value' => $this->configuration['cluster_settings']['cluster'] ? $this->configuration['cluster_settings']['cluster'] : '',
      '#description' => t('Select the cluster you want to handle the connections.'),
    );
    return $form;
  }

  /**
   * Overrides validConfigurationForm().
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * Overrides submitConfigurationForm().
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function supportsFeature($feature) {
    // First, check the features we always support.
    $supported = array(
      'search_api_autocomplete',
      'search_api_facets',
      'search_api_facets_operator_or',
      'search_api_grouping',
      'search_api_mlt',
      //'search_api_multi',
      //'search_api_service_extra',
      //'search_api_spellcheck',
      //'search_api_data_type_location',
      //'search_api_data_type_geohash',
    );
    $supported = array_combine($supported, $supported);
    if (isset($supported[$feature])) {
      return TRUE;
    }
  }

  /**
   * Creates a connection to the Elasticsearch server as configured in $this->configuration.
   */
  protected function connect() {
    if (!$this->elasticsearchClient && $this->configuration) {
      if (empty($this->configuration['cluster_settings']['cluster'])) {
        $cluster = Cluster::getDefaultCluster();
      }
      else {
        $cluster = $this->configuration['cluster_settings']['cluster'];
      }

      $this->clusterEntity = Cluster::load($cluster);
      $this->elasticsearchClient = Cluster::getClientInstance($this->clusterEntity);
    }
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
    $info = array();

    $serverlink = $this->getServerLink();
    $info[] = array(
      'label' => $this->t('Elasticsearch server URI'),
      'info' => \Drupal::l($serverlink, Url::fromUri($serverlink)),
    );

    if ($this->server->status()) {
      // If the server is enabled, check whether Elasticsearch can be reached.
      $ping = $this->ping();
      if ($ping) {
        $msg = $this->t('The Elasticsearch server could be reached');
      }
      else {
        $msg = $this->t('The Elasticsearch server could not be reached. Further data is therefore unavailable.');
      }
      $info[] = array(
        'label' => $this->t('Connection'),
        'info' => $msg,
        'status' => $ping ? 'ok' : 'error',
      );
    }

    return $info;
  }

  /**
   * Helper function. Parse an option form element.
   */
  protected function parseOptionFormElement($element, $key) {
    $children_keys = Element::children($element);

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
   * Returns a link to the Elasticsearch server, if the necessary options are set.
   */
  public function getServerLink() {
    if (!$this->configuration) {
      return '';
    }
    $host = $this->configuration['host'];
    if ($host == 'localhost' && !empty($_SERVER['SERVER_NAME'])) {
      $host = $_SERVER['SERVER_NAME'];
    }
    $url = $this->configuration['scheme'] . '://' . $host . ':' . $this->configuration['port'] . $this->configuration['path'];
    return $url;
  }

  /**
   * Ping the Elasticsearch server to tell whether it can be accessed.
   */
  public function ping() {
    $this->connect();
    try {
      if ($this->clusterEntity->checkClusterStatus()) {
        return TRUE;
      }
    }
    catch (\Exception $e) {
      throw $e;
    }
    return FALSE;
  }

  /**
   * Helper function. Return server options.
   */
  public function getOptions() {
    // @todo confused, where is this variable defined? Not in the class
    return $this->options;
  }

  /**
   * Helper function. Return a server option.
   */
  public function getOption($option, $default = NULL) {
    $options = $this->getOptions();
    return isset($options->$option) ? $options->option : $default;
  }

  /**
   * Get the configured cluster; if the cluster is blank, use the default.
   */
  public function getCluster() {
    $cluster_id = $this->getOption('elasticsearch_cluster', '');
    return empty($cluster_id) ? Cluster::getDefaultCluster() : $cluster_id;
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
      $value = nl2br(String::checkPlain(print_r($value, TRUE)));
    }
    $output .= '<dt><em>' . String::checkPlain($element['label']) . '</em></dt>' . "\n";
    $output .= '<dd>' . $value . '</dd>' . "\n";

    return "<dl>\n{$output}</dl>";
  }


  /**
   * Overrides addIndex().
   */
  public function addIndex(IndexInterface $index) {
    $this->connect();
    $index_name = $this->getIndexName($index);
    if (!empty($index_name)) {
      try {
        $client = $this->elasticsearchClient;
        if (!$client->indices()->exists(array('index' => $index_name))) {
          $params = array(
            // TODO: Add the timeout option.
            'index' => $index_name,
            'body' => array(
              'settings' => array(
                'number_of_shards' => isset($index->options['number_of_shards']) ? $index->options['number_of_shards'] : 5 ,
                'number_of_replicas' => isset($index->options['number_of_replicas']) ? $index->options['number_of_replicas'] : 1,
              )
            )
          );
          $response = $client->indices()->create($params);
          if (!Cluster::elasticsearchCheckResponseAck($response)) {
            drupal_set_message(t('The elasticsearch client wasn\'t able to create index'), 'error');
          }
        }

         // Update mapping.
        $this->fieldsUpdated($index);
      }
      catch (\Exception $e) {
        drupal_set_message($e->getMessage(), 'error');
      }
    }
  }

  /**
   * Overrides fieldsUpdated().
   */
  public function fieldsUpdated(IndexInterface $index) {
    $this->connect();
    $params = $this->getIndexParam($index, TRUE);
    $properties = array(
      'id' => array('type' => 'string', 'index' => 'not_analyzed', 'include_in_all' => FALSE),
    );

    // Map index fields.
    /** @var \Drupal\search_api\Item\FieldInterface[] $field_data */
    foreach ($index->getFields() as $field_id => $field_data) {
      $properties[$field_id] = $this->getFieldMapping($field_data);
    }

    try {
      if ($this->elasticsearchClient->getIndices()->existsType($params)) {
        $current_mapping = $this->elasticsearchClient->getIndices()->getMapping($params);
        if (!empty($current_mapping)) {
          // If the mapping exits, delete it to be able to re-create it.
          $this->elasticsearchClient->getIndices()->deleteMapping($params);
        }
      }

      // TODO: We need also:
      // $params['index'] - (Required)
      // ['type'] - The name of the document type
      // ['timeout'] - (time) Explicit operation timeout
      $params['body'][$params['type']]['properties'] = $properties;
      $response = $this->elasticsearchClient->getIndices()->putMapping($params);
      if (!Cluster::elasticsearchCheckResponseAck($response)) {
        drupal_set_message(t('Cannot create the mapping of the fields!'), 'error');
      }
    }
    catch (\Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Helper function to return the index param.
   * @param IndexInterface $index
   * @return array
   */
  protected function getIndexParam(IndexInterface $index, $with_type = FALSE) {
    $index_name = $this->getIndexName($index);

    $params = array();
    $params['index'] = $index_name;

    if ($with_type) {
      $params['type'] = $index->id();
    }

    return $params;
  }

  /**
   * Overrides removeIndex().
   */
  public function removeIndex($index) {
    $this->connect();
    $params = $this->getIndexParam($index);

    try {
      $response = $this->elasticsearchClient->getIndices()->delete($params);
    }
    catch (\Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
    }
  }

  /**
   * Helper function, check if the type exists.
   * @param IndexInterface $index
   * @return boolean
   */
  protected function getElasticsearchTypeExists(IndexInterface $index) {
    $this->connect();
    $params = $this->getIndexParam($index, TRUE);
    try {
      return $this->elasticsearchClient->getIndices()->existsType($params);
    }
    catch (\Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
      return FALSE;
    }
  }

  /**
   * Overrides indexItems().
   */
  public function indexItems(IndexInterface $index, array $items) {
    $this->connect();
    $elastic_type_exists = $this->getElasticsearchTypeExists($index);
    /*
    if (empty($elastic_type_exists) || empty($items)) {
      return array();
    }
    */
    if (empty($items)) {
      return array();
    }

    // TODO: We need to handle the following params as well:
    // ['consistency'] = (enum) Explicit write consistency setting for the operation
    // ['refresh']     = (boolean) Refresh the index after performing the operation
    // ['replication'] = (enum) Explicitly set the replication type
    // ['fields']      = (list) Default comma-separated list of fields to return in the response for updates
    $params = $this->getIndexParam($index, TRUE);

    /** @var \Drupal\search_api\Item\ItemInterface[] $items */
    foreach ($items as $id => $item) {
      $data = array('id' => $id);
      /** @var \Drupal\search_api\Item\FieldInterface $field */
      foreach ($item as $name => $field) {
        $data[$field->getFieldIdentifier()] = $field->getValues();
      }
      $params['body'][] = array('index' => array('_id' => $data['id']));
      $params['body'][] = $data;
    }

    try {
      $response = $this->elasticsearchClient->bulk($params);
      // If error throw the error we have.
      if (!empty($response['errors'])) {
        foreach($response['items'] as $item) {
          if (!empty($item['index']['status']) && $item['index']['status'] == '400') {
            // TODO: This foreach maybe is better to return only the indexed items for return
            // instead of throwing an error and stop the process cause we are in bulk
            // and some of the items can be indexed successfully.
            throw new SearchApiException($item['index']['error']['reason'] . '. ' . $item['index']['error']['caused_by']['reason']);
          }
        }
      }
    }
    catch (\Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
    }

    return array_keys($items);
  }

  /**
   * Prefixes an index ID as configured.
   *
   * The resulting ID will be a concatenation of the following strings:
   * - If set, the "elasticsearch.settings.index_prefix" configuration.
   * - If set, the index-specific "elasticsearch.settings.index_prefix_INDEX"
   *   configuration.
   * - The index's machine name.
   *
   * @param string $machine_name
   *   The index's machine name.
   *
   * @return string
   *   The prefixed machine name.
   */
  protected function getIndexId($machine_name) {
    // Prepend per-index prefix.
    $id = $this->elasticsearchSettings->get('index_prefix_' . $machine_name) . $machine_name;
    // Prepend environment prefix.
    $id = $this->elasticsearchSettings->get('index_prefix') . $id;
    return $id;
  }

  /**
   * Creates an ID used as the unique identifier at the Solr server.
   *
   * This has to consist of both index and item ID. Optionally, the site hash is
   * also included.
   *
   * @see elasticsearch_site_hash()
   */
  protected function createId($index_id, $item_id) {
    $site_hash = !empty($this->configuration['site_hash']) ? elasticsearch_site_hash() . '-' : '';
    return "$site_hash$index_id-$item_id";
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAllIndexItems(IndexInterface $index) {
    $this->removeIndex($index);
    $this->addIndex($index);
  }

  /**
   * Overrides deleteItems().
   */
  public function deleteItems(IndexInterface $index = NULL, array $ids) {
    if ($ids === 'all') {
      $this->deleteAllIndexItems($index);
    }
    else {
      $this->deleteItemsIds($ids, $index);
    }
  }

  /**
   * Helper function for bulk delete operation.
   *
   * @param array $ids
   * @param IndexInterface $index
   *
   * TODO: Test function if working.
   *
   */
  private function deleteItemsIds($ids, IndexInterface $index = NULL) {
    $this->connect();
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
    catch (\Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
    }
  }


  /**
   * Overrides search().
   */
  public function search(QueryInterface $query) {
    // Results.
    $search_result = SearchApiUtility::createSearchResultSet($query);

    // Get index
    $index = $query->getIndex();

    $params = $this->getIndexParam($index, TRUE);

    // Check elasticsearch index.
    $this->connect();
    if (!$this->elasticsearchClient->getIndices()->existsType($params)) {
      return $search_result;
    }
    
    $query->setOption('ElasticParams', $params);

    // Build Elastica query.
    $params = $this->buildSearchQuery($query);

    // Add facets.
    //$this->addSearchFacets($params, $query);

    // Do search.
    $response = $this->elasticsearchClient->search($params);

    // Parse response.
    return $this->parseSearchResponse($response, $query);
  }

  /**
   * Recursively parse Search API filters.
   */
  protected function parseFilter(FilterInterface $query_filter, $index_fields, $ignored_field_id = '') {

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
          elseif ($filter_info instanceof FilterInterface) {
            $nested_filters = $this->parseFilter($filter_info, $index_fields, $ignored_field_id);
            // TODO: handle error. - here is unnecessary cause in if we thow exceptions and this is still in try{}  .
            if (!empty($nested_filters)) {
              $filters = array_merge($filters, $nested_filters);
            }
          }
        }
        $filters = $this->setFiltersConjunction($filters, $conjunction);
      }
      catch (\Exception $e) {
        watchdog('Elasticsearch Search API', String::checkPlain($e->getMessage()), array(), WATCHDOG_ERROR);
        drupal_set_message(String::checkPlain($e->getMessage()), 'error');
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
          throw new \Exception(t('Value is empty for :field_id! Incorrect filter criteria is using for searching!', array(':field_id' => $filter_assoc['field_id'])));
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
          throw new \Exception(t('Undefined operator :field_operator for :field_id field! Incorrect filter criteria is using for searching!',
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
        throw new \Exception(t('Undefined conjunction :conjunction! Available values are :avail_conjunction! Incorrect filter criteria is using for searching!',
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
      throw new \Exception(t(':field_id Undefined field ! Incorrect filter criteria is using for searching!', array(':field_id' => $field_id)));
    }

    // Check operator.
    if (empty($filter_assoc['filter_operator'])) {
      throw new \Exception(t('Empty filter operator for :field_id field! Incorrect filter criteria is using for searching!', array(':field_id' => $field_id)));
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

    foreach ($keys as $key_nr => $key) {
      // We cannot use \Drupal\Core\Render\Element::children() anymore because
      // $keys is not a valid render array.
      if ($key_nr[0] === '#' || !$key) {
        continue;
      }

      if (is_array($key)) {
        $values[] = $this->flattenKeys($key);
      }
      elseif (is_string($key)) {
        // If parse mode is not "direct": quote the keyword.
        if ($parse_mode !== 'direct') {
          $key = '"' . $key . '"';
        }

        $values[] = $key;
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
  public function getIndexName(IndexInterface $index) {
    global $databases;

    $site_database = $databases['default']['default']['database'];

    $index_machine_name = is_string($index) ? $index : $index->id();

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
  public function getFieldMapping(FieldInterface $field) {
    try{
      $type = $field->getType();

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
    catch (\Exception $e) {
      watchdog('Elasticsearch Backend', String::checkPlain($e->getMessage()), array(), WATCHDOG_ERROR);
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
   * Helper function. Return index fields.
   */
  public function getIndexFields(QueryInterface $query) {
    $index = $query->getIndex();
    $index_fields = $index->getFields();
    return $index_fields;
  }

  /**
   * Helper function build search query().
   */
  protected function buildSearchQuery(QueryInterface $query) {
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
  protected function getSearchQueryOptions(QueryInterface $query) {

    // Query options.
    $query_options = $query->getOptions();

    // Index fields.
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

      // Query string.
      $search_string = $this->flattenKeys($keys, $query_options['parse mode']);

      if (!empty($search_string)) {
        $query_search_string = array('query_string' => array());
        $query_search_string['query_string']['query'] = $search_string;
        $query_search_string['query_string']['fields'] = array_values($query_full_text_fields);
        $query_search_string['query_string']['analyzer'] = 'snowball';
      }
    }

    $sort = NULL;
    // Sort.
    try {
      // TODO: Why we are calling SolrSearchQuery?
      $sort = $this->getSortSearchQuery($query);
    }
    catch (\Exception $e) {
      // watchdog_exception('Elasticsearch Search API', String::checkPlain($e->getMessage()), array(), WATCHDOG_ERROR);
      drupal_set_message($e->getMessage(), 'error');
    }

    // Filters.
    $parsed_query_filters = $this->parseFilter($query->getFilter(), $index_fields);
    if (!empty($parsed_query_filters)) {
      $query_search_filter = $parsed_query_filters[0];
    }

    // More Like This.
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
  protected function getSortSearchQuery(QueryInterface $query) {

    $index_fields = $this->getIndexFields($query);
    $sort = array();
    foreach ($query->getSorts() as $field_id => $direction) {
      $direction = Unicode::strtolower($direction);

      if ($field_id === 'search_api_relevance') {
        $sort['_score'] = $direction;
      }
      elseif ($field_id === 'search_api_id') {
        $sort['id'] = $direction;
      }
      elseif (isset($index_fields[$field_id])) {
        $sort[$field_id] = $direction;
      }
      else {
        throw new \Exception(t('Incorrect sorting!.'));
      }
    }
    return $sort;
  }

  /**
   * Helper function build facets in search.
   */
  protected function addSearchFacets(array &$params, QueryInterface $query) {

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

        // TODO: handle different types (GeoDistance and so on). See the
        // supportedFeatures todo.
        if ($field_type === 'date') {
          $facet_type = 'date_histogram';
          $facet[$field_id] = $this->createDateFieldFacet($field_id, $facet);
        }
        else {
          $facet_type = 'terms';
          $facet[$field_id][$facet_type]['all_terms'] = (bool) $facet_info['missing'];
        }

        // Add the facet.
        if (!empty($facet[$field_id])) {
          // Add facet options.
          $facet_info['facet_type'] = $facet_type;
          $facet[$field_id] = $this->addFacetOptions($facet[$field_id], $query, $facet_info);
        }
        $params['body']['facets'][$field_id] = $facet[$field_id];
      }
    }
  }

  /**
   * Helper function that add options and return facet.
   */
  protected function addFacetOptions(&$facet, QueryInterface $query, $facet_info) {
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
  protected function getFacetSearchFilter(QueryInterface $query, $facet_info) {
    $index_fields = $this->getIndexFields($query);
    $facet_search_filter = '';

    if (isset($facet_info['operator']) && \Unicode::strtolower($facet_info['operator']) == 'or') {
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
    // TODO: Check the timezone cause this way of hardcoding doesn't seem right.
    $result['date_histogram']['time_zone'] = 'UTC';
    // Use factor 1000 as we store dates as seconds from epoch
    // not milliseconds.
    $result['date_histogram']['factor'] = 1000;

    return $result;
  }

  /**
   * Helper function that return facet limits.
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
  public function parseSearchResponse(array $response, QueryInterface $query) {
    $index = $query->getIndex();

    // Set up the results array.
    $results = SearchApiUtility::createSearchResultSet($query);
    $results->setExtraData('elasticsearch_response', $response);
    $results->setResultCount($response['hits']['total']);

    // Add each search result to the results array.
    if (!empty($response['hits']['hits'])) {
      foreach ($response['hits']['hits'] as $result) {
        $result_item = SearchApiUtility::createItem($index, $result['_id']);
        $result_item->setScore($result['_score']);

        // Set each item in _source as a field in Search API.
        foreach ($result['_source'] as $elasticsearch_property_id => $elasticsearch_property) {
          // Make everything a multifield.
          if (!is_array($elasticsearch_property)) {
            $elasticsearch_property = array($elasticsearch_property);
          }
          $field = SearchApiUtility::createField($index, $elasticsearch_property_id);
          $field->setValues($elasticsearch_property);
          $result_item->setField($elasticsearch_property_id, $field);
        }
        // @todo: Add excerpt handling
        $results->addResultItem($result_item);
      }
    }

    return $results;
  }

  /**
   * Helper function that parse facets.
   */
  protected function parseSearchFacets($response, QueryInterface $query) {

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
   * @param QueryInterface $query
   * @param SearchApiAutocompleteSearch $search
   * @param string $incomplete_key
   * @param string $user_input
   */
  public function getAutocompleteSuggestions(QueryInterface $query, SearchApiAutocompleteSearch $search, $incomplete_key, $user_input) {
    $suggestions = array();
    // Turn inputs to lower case, otherwise we get case sensitivity problems.
    $incomp = \Unicode::strtolower($incomplete_key);

    $index = $query->getIndex();
    $index_fields = $this->getIndexFields($query);

    $complete = $query->getOriginalKeys();
    $query->keys($user_input);

    try {
      // TODO: Make autocomplete to work as autocomplete instead of exact string
      // match.
      $response = $this->search($query);
    }
    catch (\Exception $e) {
      watchdog('Elasticsearch Search API', String::checkPlain($e->getMessage()), array(), WATCHDOG_ERROR);
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

  /* TODO: Implement the settings update feature. */

}
