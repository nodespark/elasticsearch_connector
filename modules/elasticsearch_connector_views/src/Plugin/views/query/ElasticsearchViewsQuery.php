<?php

namespace Drupal\elasticsearch_connector_views\Plugin\views\query;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\join\JoinPluginBase;
use Drupal\views\Plugin\views\query\QueryPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a Views query class for searching on Search API indexes.
 *
 * @ViewsQuery(
 *   id = "elasticsearch_connector_views_query",
 *   title = @Translation("Elasticsearch Connector Views Query"),
 *   help = @Translation("The query will be generated and run using the
 *   Elasticsearch API.")
 * )
 */
class ElasticsearchViewsQuery extends QueryPluginBase {

  /**
   * Number of results to display.
   *
   * @var int
   */
  protected $limit;

  /**
   * Offset of first displayed result.
   *
   * @var int
   */
  protected $offset;

  /**
   * The Elasticsearch index name.
   *
   * @var string
   */
  protected $index;

  /**
   * The Elastic search cluster.
   *
   * @var \Drupal\elasticsearch_connector\Entity\Cluster
   */
  protected $elasticsearchCluster;

  /**
   * The query that will be executed.
   *
   * @var \nodespark\DESConnector\ClientInterface
   */
  protected $elasticsearchClient;

  /**
   * Array of all encountered errors.
   *
   * Each of these is fatal, meaning that a non-empty $errors property will
   * result in an empty result being returned.
   *
   * @var array
   */
  protected $errors = [];

  /**
   * Whether to abort the search instead of executing it.
   *
   * @var bool
   */
  protected $abort = FALSE;

  /**
   * The properties that should be retrieved from result items.
   *
   * The array is keyed by datasource ID (which might be NULL) and property
   * path, the values are the associated combined property paths.
   *
   * @var string[][]
   */
  protected $retrievedProperties = [];

  /**
   * The query's conditions representing the different Views filter groups.
   *
   * @var array
   */
  protected $conditions = [];

  /**
   * The conjunction with which multiple filter groups are combined.
   *
   * @var string
   */
  protected $groupOperator = 'AND';

  /**
   * The logger to use for log messages.
   *
   * @var \Psr\Log\LoggerInterface|null
   */
  protected $logger;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $plugin */
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    /** @var \Psr\Log\LoggerInterface $logger */
    $logger = $container->get('logger.factory')
      ->get('elasticsearch_connector_views');
    $plugin->setLogger($logger);

    $entity_type_manager = $container->get('entity_type.manager');
    $plugin->setEntityTypeManager($entity_type_manager);

    return $plugin;
  }

  /**
   * Retrieves the logger to use for log messages.
   *
   * @return \Psr\Log\LoggerInterface
   *   The logger to use.
   */
  public function getLogger() {
    return $this->logger ?: \Drupal::logger('elasticsearch_connector_views');
  }

  /**
   * Sets the logger to use for log messages.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The new logger.
   *
   * @return $this
   */
  public function setLogger(LoggerInterface $logger) {
    $this->logger = $logger;
    return $this;
  }

  /**
   * Sets the entity type manager service.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function setEntityTypeManager(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    try {
      parent::init($view, $display, $options);
      $view_id = $this->view->storage->get('base_table');
      $data = Views::viewsData()->get($view_id);
      $cluster_id = $data['table']['base']['cluster_id'];
      $this->index = $data['table']['base']['index'];
      $this->elasticsearchCluster = $this->entityTypeManager->getStorage('elasticsearch_cluster')->load($cluster_id);
      $clientManager = \Drupal::service('elasticsearch_connector.client_manager');
      $this->elasticsearchClient = $clientManager->getClientForCluster($this->elasticsearchCluster);
    }
    catch (\Exception $e) {
      $this->abort($e->getMessage());
    }
  }

  /**
   * Adds a field to list of fields.
   *
   * @param string $table
   *   Table name.
   * @param string $field
   *   Field name.
   * @param string $alias
   *   Alias.
   * @param array $params
   *   Params array.
   */
  public function addField($table, $field, $alias = '', array $params = []) {
    $this->fields[$field] = $field;
  }

  /**
   * Ensures a table exists in the queue.
   *
   * If it already exists it won't do anything, but if it does not it will add
   * the table queue. It will ensure a path leads back to the relationship
   * table.
   *
   * @param string $table
   *   The not aliased name of the table to ensure.
   * @param string $relationship
   *   The relationship to ensure the table links to. Each relationship will
   *   get a unique instance of the table being added. If not specified,
   *   will be the primary table.
   * @param \Drupal\views\Plugin\views\join\JoinPluginBase $join
   *   A Join object (or derived object) to join the alias in.
   *
   * @return null
   *   The alias used to refer to this specific table, or NULL if the table
   *   cannot be ensured.
   */
  public function ensureTable($table, $relationship = NULL, JoinPluginBase $join = NULL) {
    // TODO: Read the documentation about this.
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function defineOptions() {
    return parent::defineOptions() + [
      'bypass_access' => [
        'default' => FALSE,
      ],
      'skip_access' => [
        'default' => FALSE,
      ],
      'parse_mode' => [
        'default' => 'terms',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['bypass_access'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Bypass access checks'),
      '#description' => $this->t('If the underlying search index has access checks enabled (e.g., through the "Content access" processor), this option allows you to disable them for this view. This will never disable any filters placed on this view.'),
      '#default_value' => $this->options['bypass_access'],
    ];

    if ($this->getEntityTypes(TRUE)) {
      $form['skip_access'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Skip entity access checks'),
        '#description' => $this->t("By default, an additional access check will be executed for each entity returned by the search query. However, since removing results this way will break paging and result counts, it is preferable to configure the view in a way that it will only return accessible results. If you are sure that only accessible results will be returned in the search, or if you want to show results to which the user normally wouldn't have access, you can enable this option to skip those additional access checks. This should be used with care."),
        '#default_value' => $this->options['skip_access'],
        '#weight' => -1,
      ];
      $form['bypass_access']['#states']['visible'][':input[name="query[options][skip_access]"]']['checked'] = TRUE;
    }

    // @todo Move this setting to the argument and filter plugins where it makes
    //   more sense for users.
    $form['parse_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Parse mode'),
      '#description' => $this->t('Choose how the search keys will be parsed.'),
      '#options' => [],
      '#default_value' => $this->options['parse_mode'],
    ];

    //    foreach ($this->query->parseModes() as $key => $mode) {
    //      $form['parse_mode']['#options'][$key] = $mode['name'];
    //      if (!empty($mode['description'])) {
    //        $states['visible'][':input[name="query[options][parse_mode]"]']['value'] = $key;
    //        $form["parse_mode_{$key}_description"] = array(
    //          '#type' => 'item',
    //          '#title' => $mode['name'],
    //          '#description' => $mode['description'],
    //          '#states' => $states,
    //        );
    //      }
    //    }
  }

  /**
   * {@inheritdoc}
   */
  public function build(ViewExecutable $view) {
    $this->view = $view;

    // Store the view in the object to be able to use it later.
    $this->view = $view;

    $view->initPager();

    // Let the pager modify the query to add limits.
    $view->pager->query();

    if ($this->shouldAbort()) {
      return;
    }
    // Set aliases of the fields.
    foreach ($view->field as $field_name => &$field) {
      $field->field_alias = $field_name;
      $field->aliases['entity_type'] = 'entity_type';
    }

    // Add fields to the query so they will be shown in document.
    $this->params['fields'] = array_keys($view->field);
    $this->params['fields'][] = '_source';

    $params = [];

    $params['size'] = $view->pager->getItemsPerPage();
    $params['from'] = $view->pager->getCurrentPage() * $view->pager->getItemsPerPage();

    // If we display all items without pager remove the size limit to return
    // all documents from elasticsearch.
    if ($params['size'] == 0) {
      unset($params['size']);
    }

    // Add fields.
    // We are specifying which fields to be visible!
    $params['_source'] = [];
    if (isset($this->params['fields'])) {
      $params['_source'] = array_merge($params['_source'], $this->params['fields']);
    }

    // TODO: This should be refactored to use filters where possible.
    // TODO: The specific queries should be on filter handler level, not here.
    if (!empty($this->where['conditions'])) {
      $boolQueries = [];
      foreach ($this->where['conditions'] as $field => $value) {
        $boolQueries[]['match'] = [$field => $value];
      }
      $params['query'] = [
        'bool' => [
          'must' => $boolQueries,
        ],
      ];
    }

    // Add sorting.
    if (!empty($this->sort_fields)) {
      $params['sort'] = $this->buildSortArray();
    }

    $this->query_params = $params;

    // Export parameters for preview.
    $view->build_info['query'] = var_export($params, TRUE);
  }

  /**
   * Builds sort array.
   *
   * @return array
   *   Returns array.
   */
  protected function buildSortArray() {
    $sort = [];

    foreach ($this->sort_fields as $field => $order) {
      $sort[] = [$field => $order];
    }

    return $sort;
  }

  /**
   * Build the filter parameters for Elasticsearch.
   *
   * @param array $where
   *   All where causes for the query.
   *
   * @return array
   *   The ready to use filters in Elasticsearch body.
   */
  protected function buildFilterArray(array $where) {
    $filter = [];
    foreach ($where as $wh) {
      foreach ($wh['conditions'] as $cond) {
        $filter[mb_strtolower($wh['type'])][] = $cond['field'];
      }
    }

    if (count($filter) > 1) {
      $filter = [mb_strtolower($this->group_operator) => $filter];
    }

    return $filter;
  }

  /**
   * {@inheritdoc}
   */
  public function alter(ViewExecutable $view) {
    \Drupal::moduleHandler()->invokeAll(
      'views_query_alter', [
        $view,
        $this,
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function execute(ViewExecutable $view) {
    $view->result = [];
    $view->total_rows = 0;
    $view->execute_time = 0;

    $index = $this->getIndex();

    try {
      $start = microtime(TRUE);
      $client = $this->elasticsearchClient;
      if ($client) {
        $view->execute_time = microtime(TRUE) - $start;
      }

      // Execute the search.
      $response = $client->search([
        'index' => $index,
        'body' => $this->query_params,
      ])->getRawResponse();

      // Store results.
      if (!empty($response['hits']['hits'])) {
        $item_index = 0;
        foreach ($response['hits']['hits'] as $doc) {
          $result_doc = [];
          foreach ($doc['_source'] as $field_name => $field_value) {
            if (is_array($field_value)) {
              // TODO: Handle this by implementing the Multi-value interface in D8
              // Handle multi-value with concatenation for now.
              $result_doc[$field_name] = implode(' | ', $field_value);
            }
            else {
              $result_doc[$field_name] = $field_value;
            }
          }
          $result_doc['_source'] = $doc['_source'];
          $result_doc['index'] = $item_index;

          $view->result[] = new ResultRow($result_doc);

          // Increment the index item.
          $item_index++;
        }
      }

      // $view->result = iterator_to_array($view->result);
      // Store the results.
      $view->pager->total_items = $view->total_rows = $response['hits']['total']['value'];
      $view->pager->updatePageInfo();

      // We shouldn't use $results['performance']['complete'] here, since
      // extracting the results probably takes considerable time as well.
      $view->execute_time = $response['took'];
    }
    catch (\Exception $e) {
      $this->errors[] = $e->getMessage();
    }

    if ($this->errors) {
      foreach ($this->errors as $msg) {
        $this->messenger()->addError($msg);
      }
      $view->result = [];
      $view->total_rows = 0;
      $view->execute_time = 0;
    }
  }

  /**
   * Aborts this search query.
   *
   * Used by handlers to flag a fatal error which should not be displayed but
   * still lead to the view returning empty and the search not being executed.
   *
   * @param string|null $msg
   *   Optionally, a translated, unescaped error message to display.
   */
  public function abort($msg = NULL) {
    if ($msg) {
      $this->errors[] = $msg;
    }
    $this->abort = TRUE;
  }

  /**
   * Checks whether this query should be aborted.
   *
   * @return bool
   *   TRUE if the query should/will be aborted, FALSE otherwise.
   *
   * @see SearchApiQuery::abort()
   */
  public function shouldAbort() {
    return $this->abort;
  }

  /**
   * Retrieves the account object to use for access checks for this query.
   *
   * @return null
   *   The account for which to check access to returned or displayed entities.
   *   Or NULL to use the currently logged-in user.
   */
  public function getAccessAccount() {
    //    $account = $this->getOption('elasticsearch_connector_views_access_account');
    //    if ($account && is_scalar($account)) {
    //      $account = User::load($account);
    //    }
    return FALSE;
  }

  /**
   * Returns the Search API query object used by this Views query.
   *
   * @return null
   *   The search query object used internally by this plugin, if any has been
   *   successfully created. NULL otherwise.
   */
  public function getSearchApiQuery() {
    return $this->query;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return parent::calculateDependencies();
  }

  /**
   * Retrieves the index associated with this search.
   *
   * @return string
   *   The index this query should be executed on.
   */
  public function getIndex() {
    return $this->index;
  }

  /**
   * Gets cluster id.
   *
   * @return string
   *   Returns id.
   */
  public function getClusterId() {
    return $this->elasticsearchCluster->cluster_id;
  }

  /**
   * Gets Elasticsearch client.
   *
   * @return \nodespark\DESConnector\ClientInterface
   *   Returns Client interface.
   */
  public function getElasticsearchClient() {
    return $this->elasticsearchClient;
  }

  /**
   * TODO: Comment.
   *
   * @param string $table
   *   Table name.
   * @param null $field
   *   Field name.
   * @param string $order
   *   Type of order.
   * @param string $alias
   *   Alias paramater.
   * @param array $params
   *   Parameters array.
   */
  public function addOrderBy($table, $field = NULL, $order = 'ASC', $alias = '', array $params = []) {
    // TODO: Implement the addOrderBy method.
  }

}
