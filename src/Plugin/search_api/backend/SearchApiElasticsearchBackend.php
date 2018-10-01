<?php

namespace Drupal\elasticsearch_connector\Plugin\search_api\backend;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Config\Config;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\elasticsearch_connector\ClusterManager;
use Drupal\elasticsearch_connector\ElasticSearch\ClientManagerInterface;
use Drupal\elasticsearch_connector\ElasticSearch\Parameters\Factory\IndexFactory;
use Drupal\elasticsearch_connector\ElasticSearch\Parameters\Factory\SearchFactory;
use Drupal\elasticsearch_connector\Entity\Cluster;
use Drupal\search_api\Backend\BackendPluginBase;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSet;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api_autocomplete\SearchApiAutocompleteSearchInterface;
use Drupal\search_api_autocomplete\SearchInterface;
use Drupal\search_api_autocomplete\Suggestion\SuggestionFactory;
use Elasticsearch\Common\Exceptions\ElasticsearchException;
use nodespark\DESConnector\Elasticsearch\Aggregations\Bucket\Terms;
use nodespark\DESConnector\Elasticsearch\Aggregations\Metrics\Stats;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\search_api\Plugin\PluginFormTrait;

/**
 * Elasticsearch Search API Backend definition.
 *
 * TODO: Check for dependencies and remove them in order to properly test the
 * code.
 *
 * @SearchApiBackend(
 *   id = "elasticsearch",
 *   label = @Translation("Elasticsearch"),
 *   description = @Translation("Index items using an Elasticsearch server.")
 * )
 */
class SearchApiElasticsearchBackend extends BackendPluginBase implements PluginFormInterface {

  use PluginFormTrait;

  /**
   * Set a large integer to be the size for a "Hard limit" value of "No limit".
   */
  const FACET_NO_LIMIT_SIZE = 10000;

  /**
   * Auto fuzziness setting.
   *
   * Auto fuzziness in Elasticsearch means we don't specify a specific
   * Levenshtein distance, falling back to auto behavior. Fuzziness, including
   * auto fuzziness, is defined in the Elasticsearch documentation here:
   *
   * @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/common-options.html#fuzziness
   */
  const FUZZINESS_AUTO = 'auto';

  /**
   * Elasticsearch settings.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $elasticsearchSettings;

  /**
   * Cluster id.
   *
   * @var int
   */
  protected $clusterId;

  /**
   * Cluster object.
   *
   * @var \Drupal\elasticsearch_connector\Entity\Cluster
   */
  protected $cluster;

  /**
   * Elasticsearch client.
   *
   * @var \nodespark\DESConnector\ClientInterface
   */
  protected $client;

  /**
   * Form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Client manager service.
   *
   * @var \Drupal\elasticsearch_connector\ElasticSearch\ClientManagerInterface
   */
  protected $clientManager;

  /**
   * The cluster manager service.
   *
   * @var \Drupal\elasticsearch_connector\ClusterManager
   */
  protected $clusterManager;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Elasticsearch index factory.
   *
   * @var \Drupal\elasticsearch_connector\ElasticSearch\Parameters\Factory\IndexFactory
   */
  protected $indexFactory;

  /**
   * SearchApiElasticsearchBackend constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   Form builder service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module handler service.
   * @param \Drupal\elasticsearch_connector\ElasticSearch\ClientManagerInterface $client_manager
   *   Client manager service.
   * @param \Drupal\Core\Config\Config $elasticsearch_settings
   *   Elasticsearch settings object.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger.
   * @param \Drupal\elasticsearch_connector\ClusterManager $cluster_manager
   *   The cluster manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\elasticsearch_connector\ElasticSearch\Parameters\Factory\IndexFactory $indexFactory
   *   Index factory.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    array $plugin_definition,
    FormBuilderInterface $form_builder,
    ModuleHandlerInterface $module_handler,
    ClientManagerInterface $client_manager,
    Config $elasticsearch_settings,
    LoggerInterface $logger,
    ClusterManager $cluster_manager,
    EntityTypeManagerInterface $entity_type_manager,
    IndexFactory $indexFactory
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->formBuilder = $form_builder;
    $this->moduleHandler = $module_handler;
    $this->clientManager = $client_manager;
    $this->logger = $logger;
    $this->elasticsearchSettings = $elasticsearch_settings;
    $this->clusterManager = $cluster_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->indexFactory = $indexFactory;

    if (empty($this->configuration['cluster_settings']['cluster'])) {
      $this->configuration['cluster_settings']['cluster'] = $this->clusterManager->getDefaultCluster();
    }

    $this->cluster = $this->entityTypeManager->getStorage('elasticsearch_cluster')->load(
      $this->configuration['cluster_settings']['cluster']
    );

    if (!isset($this->cluster)) {
      throw new SearchApiException($this->t('Cannot load the Elasticsearch cluster for your index.'));
    }

    $this->client = $this->clientManager->getClientForCluster(
      $this->cluster
    );

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
      $container->get('elasticsearch_connector.client_manager'),
      $container->get('config.factory')->get('elasticsearch.settings'),
      $container->get('logger.factory')->get('elasticconnector_sapi'),
      $container->get('elasticsearch_connector.cluster_manager'),
      $container->get('entity_type.manager'),
      $container->get('elasticsearch_connector.index_factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'cluster_settings' => [
        'cluster' => '',
      ],
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
      'fuzziness' => self::FUZZINESS_AUTO,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    if (!$this->server->isNew()) {
      $server_link = $this->cluster->getSafeUrl();
      // Editing this server.
      $form['server_description'] = [
        '#type' => 'item',
        '#title' => $this->t('Elasticsearch Cluster'),
        '#description' => Link::fromTextAndUrl($server_link, Url::fromUri($server_link)),
      ];
    }
    $form['cluster_settings'] = [
      '#type' => 'fieldset',
      '#title' => t('Elasticsearch settings'),
    ];

    // We are not displaying disabled clusters.
    $clusters = $this->clusterManager->loadAllClusters(FALSE);
    $options = [];
    foreach ($clusters as $key => $cluster) {
      $options[$key] = $cluster->cluster_id;
    }

    $options[$this->clusterManager->getDefaultCluster()] = t('Default cluster: @name', ['@name' => $this->clusterManager->getDefaultCluster()]);
    $form['cluster_settings']['cluster'] = [
      '#type' => 'select',
      '#title' => t('Cluster'),
      '#required' => TRUE,
      '#options' => $options,
      '#default_value' => $this->configuration['cluster_settings']['cluster'] ? $this->configuration['cluster_settings']['cluster'] : '',
      '#description' => t('Select the cluster you want to handle the connections.'),
    ];

    $fuzziness_options = [
      '' => $this->t('- Disabled -'),
      self::FUZZINESS_AUTO => self::FUZZINESS_AUTO,
    ];
    $fuzziness_options += array_combine(range(0, 5), range(0, 5));
    $form['fuzziness'] = [
      '#type' => 'select',
      '#title' => t('Fuzziness'),
      '#required' => TRUE,
      '#options' => $fuzziness_options,
      '#default_value' => $this->configuration['fuzziness'],
      '#description' => $this->t('Some queries and APIs support parameters to allow inexact fuzzy matching, using the fuzziness parameter. See <a href="https://www.elastic.co/guide/en/elasticsearch/reference/5.6/common-options.html#fuzziness">Fuzziness</a> for more information.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * TODO: implement 'search_api_multi',
   * TODO: implement 'search_api_service_extra',
   * TODO: implement 'search_api_spellcheck',
   * TODO: implement 'search_api_data_type_location',
   * TODO: implement 'search_api_data_type_geohash',
   */
  public function getSupportedFeatures() {
    // First, check the features we always support.
    return [
      'search_api_autocomplete',
      'search_api_facets',
      'search_api_facets_operator_or',
      'search_api_grouping',
      'search_api_mlt',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function viewSettings() {
    $info = [];

    $server_link = $this->cluster->getSafeUrl();
    $info[] = [
      'label' => $this->t('Elasticsearch server URI'),
      'info' => Link::fromTextAndUrl($server_link, Url::fromUri($server_link)),
    ];

    if ($this->server->status()) {
      // If the server is enabled, check whether Elasticsearch can be reached.
      $ping = $this->client->isClusterOk();
      if ($ping) {
        $msg = $this->t('The Elasticsearch server could be reached');
      }
      else {
        $msg = $this->t('The Elasticsearch server could not be reached. Further data is therefore unavailable.');
      }
      $info[] = [
        'label' => $this->t('Connection'),
        'info' => $msg,
        'status' => $ping ? 'ok' : 'error',
      ];
    }

    return $info;
  }

  /**
   * Get the configured cluster; if the cluster is blank, use the default.
   *
   * @return string
   *   The name of the configured cluster.
   */
  public function getCluster() {
    return $this->configuration['cluster_settings']['cluster'];
  }

  /**
   * Get the configured fuzziness value.
   *
   * @return string
   *   The configured fuzziness value.
   */
  public function getFuzziness() {
    return $this->configuration['fuzziness'];
  }

  /**
   * {@inheritdoc}
   */
  public function addIndex(IndexInterface $index) {
    $this->updateIndex($index);
  }

  /**
   * {@inheritdoc}
   */
  public function updateIndex(IndexInterface $index) {
    try {
      if (!$this->client->indices()->exists($this->indexFactory->index($index))) {
        $response = $this->client->indices()->create(
          $this->indexFactory->create($index)
        );
        if (!$this->client->CheckResponseAck($response)) {
          drupal_set_message($this->t('The elasticsearch client was not able to create index'), 'error');
        }
      }

      // Update mapping.
      $this->fieldsUpdated($index);
    }
    catch (ElasticsearchException $e) {
      drupal_set_message($e->getMessage(), 'error');
    }
  }

  /**
   * Create or re-create the given index's field mapping.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   Index to update fields for.
   *
   * @return bool
   *   TRUE on success, FALSE otherwise.
   */
  public function fieldsUpdated(IndexInterface $index) {
    $params = $this->indexFactory->index($index, TRUE);

    try {
      if ($this->client->indices()->existsType($params)) {
        $current_mapping = $this->client->indices()->getMapping($params);
        if (!empty($current_mapping)) {
          try {
            // If the mapping exits, delete it to be able to re-create it.
            $this->client->indices()->deleteMapping($params);
          }
          catch (ElasticsearchException $e) {
            // If the mapping exits, delete the index and recreate it.
            // In Elasticsearch 2.3 it is not possible to delete a mapping,
            // so don't use $this->client->indices()->deleteMapping as doing so
            // will throw an exception.
            $this->removeIndex($index);
            $this->addIndex($index);
          }
        }
      }

      $response = $this->client->indices()->putMapping(
        $this->indexFactory->mapping($index)
      );

      if (!$this->client->CheckResponseAck($response)) {
        drupal_set_message(t('Cannot create the mapping of the fields!'), 'error');
      }
    }
    catch (ElasticsearchException $e) {
      drupal_set_message($e->getMessage(), 'error');
      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function removeIndex($index) {
    $params = $this->indexFactory->index($index);

    try {
      if ($this->client->indices()->exists($params)) {
        $this->client->indices()->delete($params);
      }
    }
    catch (ElasticsearchException $e) {
      drupal_set_message($e->getMessage(), 'error');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function indexItems(IndexInterface $index, array $items) {
    $elastic_type_exists = $this->doesTypeExists($index);

    if (empty($elastic_type_exists) || empty($items)) {
      return array();
    }

    try {
      $response = $this->client->bulk(
        $this->indexFactory->bulkIndex($index, $items)
      );
      // If there were any errors, log them and throw an exception.
      if (!empty($response['errors'])) {
        foreach ($response['items'] as $item) {
          if (!empty($item['index']['status']) && $item['index']['status'] == '400') {
            $this->logger->error('%reason. %caused_by for id: %id', [
              '%reason' => $item['index']['error']['reason'],
              '%caused_by' => $item['index']['error']['caused_by']['reason'],
              '%id' => $item['index']['_id'],
            ]);
          }
        }

        throw new SearchApiException($this->t('An error occurred during indexing. Check your watchdog for more information.'));
      }
    }
    catch (ElasticsearchException $e) {
      drupal_set_message($e->getMessage(), 'error');
    }

    return array_keys($items);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAllIndexItems(IndexInterface $index, $datasource_id = NULL) {
    $this->removeIndex($index);
    $this->addIndex($index);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteItems(IndexInterface $index = NULL, array $ids) {
    if (!count($ids)) {
      return;
    }

    try {
      $this->client->bulk(
        $this->indexFactory->bulkDelete($index, $ids)
      );
    }
    catch (ElasticsearchException $e) {
      drupal_set_message($e->getMessage(), 'error');
    }
  }

  /**
   * Implements SearchApiAutocompleteInterface::getAutocompleteSuggestions().
   *
   * Note that the interface is not directly implemented to avoid a dependency
   * on search_api_autocomplete module.
   */
  public function getAutocompleteSuggestions(QueryInterface $query, SearchInterface $search, $incomplete_key, $user_input) {
    try {
      $fields = $this->getQueryFulltextFields($query);
      if (count($fields) > 1) {
        throw new \LogicException('Elasticsearch requires a single fulltext field for use with autocompletion! Please adjust your configuration.');
      }
      $query->setOption('autocomplete', $incomplete_key);
      $query->setOption('autocomplete_field', reset($fields));

      // Disable facets so it does not collide with autocompletion results.
      $query->setOption('search_api_facets', FALSE);

      $result = $this->search($query);
      $query->postExecute($result);

      // Parse suggestions out of the response.
      $suggestions = [];
      $factory = new SuggestionFactory($user_input);

      $response = $result->getExtraData('elasticsearch_response');
      if (isset($response['aggregations']['autocomplete']['buckets'])) {
        $suffix_start = strlen($user_input);
        $buckets = $response['aggregations']['autocomplete']['buckets'];
        foreach ($buckets as $bucket) {
          $suggestions[] = $factory->createFromSuggestionSuffix(substr($bucket['key'], $suffix_start), $bucket['doc_count']);
        }
      }
      return $suggestions;
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
      return array();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function search(QueryInterface $query) {
    // Results.
    $search_result = $query->getResults();

    // Get index.
    $index = $query->getIndex();

    $params = $this->indexFactory->index($index, TRUE);

    // Check Elasticsearch index.
    if (!$this->client->indices()->existsType($params)) {
      return $search_result;
    }

    // Add the facets to the request.
    if ($query->getOption('search_api_facets')) {
      $this->addFacets($query);
    }

    // Build Elasticsearch query.
    $params = SearchFactory::search($query);

    // Note that this requires fielddata option to be enabled.
    // @see ::getAutocompleteSuggestions()
    // @see \Drupal\elasticsearch_connector\ElasticSearch\Parameters\Factory\IndexFactory::mapping()
    if ($incomplete_key = $query->getOption('autocomplete')) {
      // Autocomplete suggestions are determined using a term aggregation (like
      // facets), but filtered to only include facets with the right prefix.
      // As the search facet field is analyzed, facets are tokenized terms and
      // all in lower case. To match that, we need convert the our filter to
      // lower case also.
      $incomplete_key = strtolower($incomplete_key);
      // Note that we cannot use the elasticsearch client aggregations API as
      // it does not support the "include" parameter.
      $params['body']['aggs']['autocomplete']['terms'] = [
        'field' => $query->getOption('autocomplete_field'),
        'include' => $incomplete_key . '.*',
      ];
    }

    try {
      // Allow modules to alter the Elastic Search query.
      $this->preQuery($query);

      // Do search.
      $response = $this->client->search($params)->getRawResponse();
      $results = SearchFactory::parseResult($query, $response);

      // Handle the facets result when enabled.
      if ($query->getOption('search_api_facets')) {
        $this->parseFacets($results, $query);
      }

      // Allow modules to alter the Elastic Search Results.
      $this->postQuery($results, $query, $response);

      return $results;
    }
    catch (\Exception $e) {
      watchdog_exception('Elasticsearch API', $e);
      return $search_result;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailable() {
    return $this->client->isClusterOk();
  }

  /**
   * Fill the aggregation array of the request.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   Search API query.
   */
  protected function addFacets(QueryInterface $query) {
    foreach ($query->getOption('search_api_facets') as $key => $facet) {
      $facet += ['type' => NULL];

      $object = NULL;

      // @todo Add more options.
      switch ($facet['type']) {
        case 'stats':
          $object = new Stats($key, $key);
          break;

        default:
          $object = new Terms($key, $key);

          // Limit the number of facets in the result according the to facet
          // setting. A limit of 0 means no limit. Elasticsearch doesn't have a
          // way to set no limit, so we set a large integer in that case.
          $size = $facet['limit'] ? $facet['limit'] : self::FACET_NO_LIMIT_SIZE;
          $object->setSize($size);

          // Set global scope for facets with 'OR' operator.
          if ($facet['operator'] == 'or') {
            $object->setGlobalScope(TRUE);
          }
      }

      if (!empty($object)) {
        $this->client->aggregations()->setAggregation($object);
      }
    }
  }

  /**
   * Parse the result set and add the facet values.
   *
   * @param \Drupal\search_api\Query\ResultSet $results
   *   Result set, all items matched in a search.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   Search API query object.
   */
  protected function parseFacets(ResultSet $results, QueryInterface $query) {
    $response = $results->getExtraData('elasticsearch_response');
    $facets = $query->getOption('search_api_facets');

    // Create an empty array that will be attached to the result object.
    $attach = [];

    foreach ($facets as $key => $facet) {
      $terms = [];

      // Handle 'and' operator.
      if ($facet['operator'] == 'and' || ($facet['operator'] == 'or' && !isset($response['aggregations'][$key . '_global']))) {
        if (!empty($facet['type']) && $facet['type'] == 'stats') {
          $terms = $response['aggregations'][$key];
        }
        else {
          $buckets = $response['aggregations'][$key]['buckets'];
          array_walk($buckets, function ($value) use (&$terms) {
            $terms[] = [
              'count' => $value['doc_count'],
              'filter' => '"' . $value['key'] . '"',
            ];
          });
        }
      }
      elseif ($facet['operator'] == 'or') {
        if (!empty($facet['type']) && $facet['type'] == 'stats') {
          $terms = $response['aggregations'][$key . '_global'];
        }
        else {
          $buckets = $response['aggregations'][$key . '_global'][$key]['buckets'];
          array_walk($buckets, function ($value) use (&$terms) {
            $terms[] = [
              'count' => $value['doc_count'],
              'filter' => '"' . $value['key'] . '"',
            ];
          });
        }
      }
      $attach[$key] = $terms;
    }

    $results->setExtraData('search_api_facets', $attach);
  }

  /**
   * Helper function, check if the given index and type exists.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   Index object.
   *
   * @return bool
   *   TRUE if the given index exists in Elasticsearch, otherwise FALSE.
   */
  protected function doesTypeExists(IndexInterface $index) {
    $params = $this->indexFactory->index($index, TRUE);
    try {
      return $this->client->indices()->existsType($params);
    }
    catch (ElasticsearchException $e) {
      drupal_set_message($e->getMessage(), 'error');
      return FALSE;
    }
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
   * Helper function. Return date gap from two dates or timestamps.
   *
   * @param mixed $min
   *   Start date or timestamp.
   * @param mixed $max
   *   End date or timestamp.
   * @param bool $timestamp
   *   TRUE if the first two params are timestamps, FALSE otherwise. In the case
   *   of FALSE, it's assumed the first two arguments are strings and they are
   *   converted to timestamps using strtotime().
   *
   * @return string
   *   One of 'NONE', 'YEAR', 'MONTH', or 'DAY' depending on the difference
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
   * Helper function build facets in search.
   *
   * @param array $params
   *   Array of parameters to be sent in the body of a _search endpoint
   *   Elasticsearch request.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   Search API query object.
   */
  protected function addSearchFacets(array &$params, QueryInterface $query) {

    // Search API facets.
    $facets = $query->getOption('search_api_facets');
    $index_fields = $this->getIndexFields($query);

    if (!empty($facets)) {
      // Loop through facets.
      foreach ($facets as $facet_id => $facet_info) {
        $field_id = $facet_info['field'];
        $facet = [$field_id => []];

        // Skip if not recognized as a known field.
        if (!isset($index_fields[$field_id])) {
          continue;
        }
        // TODO: missing function reference.
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
   * Helper function that adds options and returns facet.
   *
   * @param array $facet
   * @param QueryInterface $query
   * @param string $facet_info
   *
   * @return array
   */
  protected function addFacetOptions(array &$facet, QueryInterface $query, $facet_info) {
    $facet_limit = $this->getFacetLimit($facet_info);
    $facet_search_filter = $this->getFacetSearchFilter($query, $facet_info);

    // Set the field.
    $facet[$facet_info['facet_type']]['field'] = $facet_info['field'];

    // OR facet. We remove filters affecting the associated field.
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
   *
   * @param QueryInterface $query
   * @param array $facet_info
   *
   * @return array|null|string
   */
  protected function getFacetSearchFilter(QueryInterface $query, array $facet_info) {
    $index_fields = $this->getIndexFields($query);

    if (isset($facet_info['operator']) && Unicode::strtolower($facet_info['operator']) == 'or') {
      $facet_search_filter = $this->parseConditionGroup($query->getConditionGroup(), $index_fields, $facet_info['field']);
      if (!empty($facet_search_filter)) {
        $facet_search_filter = $facet_search_filter[0];
      }
    }
    // Normal facet, we just use the main query filters.
    else {
      $facet_search_filter = $this->parseConditionGroup($query->getConditionGroup(), $index_fields);
      if (!empty($facet_search_filter)) {
        $facet_search_filter = $facet_search_filter[0];
      }
    }

    return $facet_search_filter;
  }

  /**
   * Helper function create Facet for date field type.
   *
   * @param mixed $facet_id
   * @param array $facet
   *
   * @return array.
   */
  protected function createDateFieldFacet($facet_id, array $facet) {
    $result = $facet[$facet_id];

    $date_interval = $this->getDateFacetInterval($facet_id);
    $result['date_histogram']['interval'] = $date_interval;
    // TODO: Check the timezone cause this hardcoded way doesn't seem right.
    $result['date_histogram']['time_zone'] = 'UTC';
    // Use factor 1000 as we store dates as seconds from epoch
    // not milliseconds.
    $result['date_histogram']['factor'] = 1000;

    return $result;
  }

  /**
   * Helper function that return facet limits.
   *
   * @param array $facet_info
   *
   * @return int|null
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
   *
   * @param mixed $facet_id
   *
   * @return string
   */
  protected function getDateFacetInterval($facet_id) {
    // Active search corresponding to this index.
    $searcher = key(facetapi_get_active_searchers());

    // Get the FacetApiAdapter for this searcher.
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
   *
   * @param $adapter
   * @param $facet_id
   *
   * @return mixed|string
   */
  public function getDateGranularity($adapter, $facet_id) {
    // Date gaps.
    $gap_weight = ['YEAR' => 2, 'MONTH' => 1, 'DAY' => 0];
    $gaps = [];
    $date_gap = 'YEAR';

    // Get the date granularity.
    if (isset($adapter)) {
      // Get the current date gap from the active date filters.
      $active_items = $adapter->getActiveItems(['name' => $facet_id]);
      if (!empty($active_items)) {
        foreach ($active_items as $active_item) {
          $value = $active_item['value'];
          if (strpos($value, ' TO ') > 0) {
            list($date_min, $date_max) = explode(' TO ', str_replace(['[', ']'], '', $value), 2);
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
   * Helper function that parse facets.
   *
   * @param array $response
   * @param QueryInterface $query
   *
   * @return array
   */
  protected function parseSearchFacets(array $response, QueryInterface $query) {

    $result = [];
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
                $result[$facet_id][] = [
                  'count' => $entry['count'],
                  'filter' => '"' . ($entry['time'] / 1000) . '"',
                ];
              }
            }
          }
          else {
            foreach ($facet_data['terms'] as $term) {
              if ($term['count'] >= $facet_min_count) {
                $result[$facet_id][] = [
                  'count' => $term['count'],
                  'filter' => '"' . $term['term'] . '"',
                ];
              }
            }
          }
        }
      }
    }

    return $result;
  }

  /**
   * Allow custom changes before sending a search query to Elastic Search.
   *
   * This allows subclasses to apply custom changes before the query is sent to
   * Solr.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The \Drupal\search_api\Query\Query object representing the executed
   *   search query.
   */
  protected function preQuery(QueryInterface $query) {
  }

  /**
   * Allow custom changes before search results are returned for subclasses.
   *
   * @param \Drupal\search_api\Query\ResultSetInterface $results
   *   The results array that will be returned for the search.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The \Drupal\search_api\Query\Query object representing the executed
   *   search query.
   * @param object $response
   *   The response object returned by Elastic Search.
   */
  protected function postQuery(ResultSetInterface $results, QueryInterface $query, $response) {
  }

  /* TODO: Implement the settings update feature. */

  /**
   * {@inheritdoc}
   */
  public function supportsDataType($type) {
    return in_array($type, ['object']);
  }

  /**
   * Implements __sleep()
   *
   * Prevents closure serialization error on search_api server add form
   */
  public function __sleep() {
    return array();
  }

}
