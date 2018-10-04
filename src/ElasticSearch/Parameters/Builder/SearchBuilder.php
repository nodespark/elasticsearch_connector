<?php

namespace Drupal\elasticsearch_connector\ElasticSearch\Parameters\Builder;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\elasticsearch_connector\ElasticSearch\Parameters\Factory\FilterFactory;
use Drupal\elasticsearch_connector\ElasticSearch\Parameters\Factory\IndexFactory;
use Drupal\search_api\ParseMode\ParseModeInterface;
use Drupal\search_api\Query\Condition;
use Drupal\search_api\Query\ConditionGroupInterface;
use Drupal\search_api\Query\QueryInterface;
use Elasticsearch\Common\Exceptions\ElasticsearchException;
use MakinaCorpus\Lucene\Query;
use MakinaCorpus\Lucene\TermCollectionQuery;
use MakinaCorpus\Lucene\TermQuery;
use Drupal\elasticsearch_connector\Event\PrepareSearchQueryEvent;
use Drupal\elasticsearch_connector\Event\BuildSearchParamsEvent;

/**
 * Class SearchBuilder.
 */
class SearchBuilder {
  use StringTranslationTrait;

  /**
   * Search API Index entity.
   *
   * @var \Drupal\search_api\Entity\Index
   */
  protected $index;

  /**
   * Search API Query object.
   *
   * @var \Drupal\search_api\Query\QueryInterface
   */
  protected $query;

  /**
   * Elasticsearch Query DSL.
   *
   * Will be converted to JSON and sent in the request body as the Elasticsearch
   * query DSL.
   *
   * @var array
   */
  protected $body;

  /**
   * ParameterBuilder constructor.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   Search API Query object.
   */
  public function __construct(QueryInterface $query) {
    $this->query = $query;
    $this->index = $query->getIndex();
    $this->body = [];
  }

  /**
   * Build up the body of the request to the Elasticsearch _search endpoint.
   *
   * @return array
   *   Array or parameters to send along to the Elasticsearch _search endpoint.
   */
  public function build() {
    // Query options.
    $params = IndexFactory::index($this->index, TRUE);
    $query_options = $this->getSearchQueryOptions();

    // Set the size and from parameters.
    $this->body['from'] = $query_options['query_offset'];
    $this->body['size'] = $query_options['query_limit'];

    // Sort.
    if (!empty($query_options['sort'])) {
      $this->body['sort'] = $query_options['sort'];
    }

    // Build the query.
    if (!empty($query_options['query_search_string']) && !empty($query_options['query_search_filter'])) {
      $this->body['query']['bool']['must'] = $query_options['query_search_string'];
      $this->body['query']['bool']['filter'] = $query_options['query_search_filter'];
    }
    elseif (!empty($query_options['query_search_string'])) {
      if (empty($this->body['query'])) {
        $this->body['query'] = [];
      }
      $this->body['query'] += $query_options['query_search_string'];
    }
    elseif (!empty($query_options['query_search_filter'])) {
      $this->body['query'] = $query_options['query_search_filter'];
    }

    // TODO: Handle fields on filter query.
    if (empty($fields)) {
      unset($this->body['fields']);
    }

    if (empty($this->body['post_filter'])) {
      unset($this->body['post_filter']);
    }

    // TODO: Fix the match_all query.
    if (empty($query_body)) {
      $query_body['match_all'] = [];
    }

    $exclude_source_fields = $this->query->getOption('elasticsearch_connector_exclude_source_fields', []);

    if (!empty($exclude_source_fields)) {
      $this->body['_source'] = [
        'excludes' => $exclude_source_fields,
      ];
    }

    // More Like This.
    $this->setMoreLikeThisQuery($query_options);

    $params['body'] = $this->body;
    // Preserve the options for further manipulation if necessary.
    $this->query->setOption('ElasticParams', $params);

    // Allow other modules to alter index config before we create it.
    $indexName = IndexFactory::getIndexName($this->index);
    $dispatcher = \Drupal::service('event_dispatcher');
    $buildSearchParamsEvent = new BuildSearchParamsEvent($params, $indexName);
    $event = $dispatcher->dispatch(BuildSearchParamsEvent::BUILD_QUERY, $buildSearchParamsEvent);
    $params = $event->getElasticSearchParams();

    return $params;
  }

  /**
   * Helper function to return associative array with query options.
   *
   * @return array
   *   Associative array with the following keys:
   *   - query_offset: Pager offset.
   *   - query_limit: Number of items to return in the query.
   *   - query_search_string: Main full text query.
   *   - query_search_filter: Filters.
   *   - sort: Sort options.
   *   - mlt: More like this search options.
   */
  protected function getSearchQueryOptions() {
    // Query options.
    $query_options = $this->query->getOptions();

    $parse_mode = $this->query->getParseMode();

    // Index fields.
    $index_fields = $this->index->getFields();

    // Search API does not provide metadata for some special fields but might
    // try to query for them. Thus add the metadata so we allow for querying
    // them.
    if (empty($index_fields['search_api_datasource'])) {
      $index_fields['search_api_datasource'] = \Drupal::getContainer()
        ->get('search_api.fields_helper')
        ->createField($this->index, 'search_api_datasource', ['type' => 'string']);
    }

    // Range.
    $query_offset = empty($query_options['offset']) ? 0 : $query_options['offset'];
    $query_limit = empty($query_options['limit']) ? 10 : $query_options['limit'];

    // Query string.
    $query_search_string = NULL;

    // Query filter.
    $query_search_filter = NULL;

    // Full text search.
    $keys = $this->query->getKeys();
    if (!empty($keys)) {
      if (is_string($keys)) {
        $keys = [$keys];
      }

      // Full text fields in which to perform the search.
      $query_full_text_fields = $this->query->getFulltextFields();
      if ($query_full_text_fields) {
        // Make sure the fields exists within the indexed fields.
        $query_full_text_fields = array_intersect($this->index->getFulltextFields(), $query_full_text_fields);
      }
      else {
        $query_full_text_fields = $this->index->getFulltextFields();
      }

      $query_fields = [];
      foreach ($query_full_text_fields as $full_text_field_name) {
        $full_text_field = $index_fields[$full_text_field_name];
        $query_fields[] = $full_text_field->getFieldIdentifier() . '^' . $full_text_field->getBoost();
      }

      // Query string.
      $lucene = $this->flattenKeys($keys, $parse_mode, $this->index->getServerInstance()->getBackend()->getFuzziness());
      $search_string = $lucene->__toString();

      if (!empty($search_string)) {
        $query_search_string = ['query_string' => []];
        $query_search_string['query_string']['query'] = $search_string;
        $query_search_string['query_string']['fields'] = $query_fields;
      }
    }

    $sort = NULL;
    // Sort.
    try {
      $sort = $this->getSortSearchQuery();
    }
    catch (ElasticsearchException $e) {
      watchdog_exception('Elasticsearch Search API', $e);
      drupal_set_message($e->getMessage(), 'error');
    }

    $languages = $this->query->getLanguages();
    if ($languages !== NULL) {
      $this->query->getConditionGroup()
        ->addCondition('_language', $languages, 'IN');
    }

    // Filters.
    try {
      $parsed_query_filters = $this->getQueryFilters(
        $this->query->getConditionGroup(),
        $index_fields
      );
      if (!empty($parsed_query_filters)) {
        $query_search_filter = $parsed_query_filters;
      }
    }
    catch (ElasticsearchException $e) {
      watchdog_exception(
        'Elasticsearch Search API',
        $e,
        Html::escape($e->getMessage())
      );
      drupal_set_message(Html::escape($e->getMessage()), 'error');
    }

    // More Like This.
    $mlt = [];
    if (isset($query_options['search_api_mlt'])) {
      $mlt = $query_options['search_api_mlt'];
    }

    $elasticSearchQuery = [
      'query_offset' => $query_offset,
      'query_limit' => $query_limit,
      'query_search_string' => $query_search_string,
      'query_search_filter' => $query_search_filter,
      'sort' => $sort,
      'mlt' => $mlt,
    ];

    // Allow other modules to alter index config before we create it.
    $indexName = IndexFactory::getIndexName($this->index);
    $dispatcher = \Drupal::service('event_dispatcher');
    $prepareSearchQueryEvent = new PrepareSearchQueryEvent($elasticSearchQuery, $indexName);
    $event = $dispatcher->dispatch(PrepareSearchQueryEvent::PREPARE_QUERY, $prepareSearchQueryEvent);
    $elasticSearchQuery = $event->getElasticSearchQuery();

    return $elasticSearchQuery;
  }

  /**
   * Turn the given search keys into a lucene object structure.
   *
   * @param array $keys
   *   Search keys, in the format described by
   *   \Drupal\search_api\ParseMode\ParseModeInterface::parseInput().
   * @param \Drupal\search_api\ParseMode\ParseModeInterface $parse_mode
   *   Search API parse mode.
   * @param bool $fuzzy
   *   Enable fuzzy support or not.
   *
   * @return \MakinaCorpus\Lucene\AbstractQuery
   *   Return a lucene query object.
   */
  protected function flattenKeys(array $keys, ParseModeInterface $parse_mode = NULL, $fuzzy = TRUE) {
    // Grab the conjunction and negation properties if present.
    $conjunction = isset($keys['#conjunction']) ? $keys['#conjunction'] : 'AND';
    $negation = !empty($keys['#negation']);

    // Create a top level query.
    $query = (new TermCollectionQuery())
      ->setOperator($conjunction);
    if ($negation) {
      $query->setExclusion(Query::OP_PROHIBIT);
    }

    // Filter out top level properties beginning with '#'.
    $keys = array_filter($keys, function ($key) {
      return $key[0] !== '#';
    }, ARRAY_FILTER_USE_KEY);

    // Loop over the keys.
    foreach ($keys as $key) {
      $element = NULL;

      if (is_array($key)) {
        $element = $this->luceneFlattenKeys($key, $parse_mode);
      }
      elseif (is_string($key)) {
        $element = (new TermQuery())
          ->setValue($key);
        if ($fuzzy) {
          $element->setFuzzyness($fuzzy);
        }
      }

      if (isset($element)) {
        $query->add($element);
      }
    }

    return $query;
  }

  /**
   * Helper function that returns sort for query in search.
   *
   * @return array
   *   Sort portion of the query.
   *
   * @throws \Exception
   */
  protected function getSortSearchQuery() {
    $index_fields = $this->index->getFields();
    $sort = [];
    $query_full_text_fields = $this->index->getFulltextFields();
    foreach ($this->query->getSorts() as $field_id => $direction) {
      $direction = Unicode::strtolower($direction);

      if ($field_id === 'search_api_relevance') {
        $sort['_score'] = $direction;
      }
      elseif ($field_id === 'search_api_id') {
        $sort['id'] = $direction;
      }
      elseif (isset($index_fields[$field_id])) {
        if (in_array($field_id, $query_full_text_fields)) {
          // Set the field that has not been analyzed for sorting.
          $sort[$field_id . '.keyword'] = $direction;
        }
        else {
          $sort[$field_id] = $direction;
        }
      }
      else {
        // TODO: no silly exceptions...
        throw new \Exception(t('Incorrect sorting!'));
      }

    }
    return $sort;
  }

  /**
   * Recursively parse Search API condition group.
   *
   * @param \Drupal\search_api\Query\ConditionGroupInterface $condition_group
   *   The condition group object that holds all conditions that should be
   *   expressed as filters.
   * @param \Drupal\search_api\Item\FieldInterface[] $index_fields
   *   An array of all indexed fields for the index, keyed by field identifier.
   *
   * @return array
   *   Array of filter parameters to apply to query based on the given Search
   *   API condition group.
   *
   * @throws \Exception
   */
  protected function getQueryFilters(ConditionGroupInterface $condition_group, array $index_fields) {
    $filters = [];
    $backend_fields = ['_language' => TRUE];

    if (!empty($condition_group)) {
      $conjunction = $condition_group->getConjunction();

      foreach ($condition_group->getConditions() as $condition) {
        $filter = NULL;

        // Simple filter [field_id, value, operator].
        if ($condition instanceof Condition) {

          if (!$condition->getField() || !$condition->getValue() || !$condition->getOperator()) {
            // TODO: When using views the sort field is coming as a filter and
            // messing with this section.
            // throw new Exception(t('Incorrect filter criteria is using for searching!'));
          }

          $field_id = $condition->getField();
          if (!isset($index_fields[$field_id]) && !isset ($backend_fields[$field_id])) {
            // TODO: proper exception.
            throw new \Exception(
              t(
                ':field_id Undefined field ! Incorrect filter criteria is using for searching!',
                [':field_id' => $field_id]
              )
            );
          }

          // Check operator.
          if (!$condition->getOperator()) {
            // TODO: proper exception.
            throw new \Exception(
              t(
                'Empty filter operator for :field_id field! Incorrect filter criteria is using for searching!',
                [':field_id' => $field_id]
              )
            );
          }

          // Check field.
          $filter = FilterFactory::filterFromCondition($condition);

          if (!empty($filter)) {
            $filters[] = $filter;
          }
        }
        // Nested filters.
        elseif ($condition instanceof ConditionGroupInterface) {
          $nested_filters = $this->getQueryFilters(
            $condition,
            $index_fields
          );

          if (!empty($nested_filters)) {
            $filters[] = $nested_filters;
          }
        }
      }

      $filters = $this->setFiltersConjunction($filters, $conjunction);
    }

    return $filters;
  }

  /**
   * Helper function to set filters conjunction.
   *
   * @param array $filters
   *   Array of filter parameters to be passed along to Elasticsearch.
   * @param string $conjunction
   *   The conjunction used by the corresponding Search API condition group –
   *   either 'AND' or 'OR'.
   *
   * @return array
   *   Returns the passed $filters array wrapped in an array keyed by 'should'
   *   or 'must', as appropriate, based on the given conjunction.
   *
   * @throws \Exception
   *   In case of an invalid $conjunction.
   */
  protected function setFiltersConjunction(array &$filters, $conjunction) {
    if ($conjunction === 'OR') {
      $filters = ['should' => $filters];
    }
    elseif ($conjunction === 'AND') {
      $filters = ['must' => $filters];
    }
    else {
      throw new \Exception(
        t(
          'Undefined conjunction :conjunction! Available values are :avail_conjunction! Incorrect filter criteria is using for searching!',
          [
            ':conjunction!' => $conjunction,
            ':avail_conjunction' => $conjunction,
          ]
        )
      );
    }

    return ['bool' => $filters];
  }

  /**
   * Setup the More like this clause of the Elasticsearch query.
   *
   * Adjusts $this->body to have a more like this query.
   *
   * @param array $query_options
   *   Array of query options. We're most interested here in the key of 'mlt',
   *   which should contain the following keys:
   *   - id: To be used as the like_text in the more_like_this query.
   *   - fields: Array of fields.
   */
  protected function setMoreLikeThisQuery(array $query_options) {
    if (!empty($query_options['mlt'])) {
      $mlt_query['more_like_this'] = [];

      // Transform input parameter "id" to "ids" if available.
      if (isset($query_options['mlt']['id'])) {
        $query_options['mlt']['ids'] =
          is_array($query_options['mlt']['id']) ?
            $query_options['mlt']['id'] :
            [$query_options['mlt']['id']];
        unset($query_options['mlt']['id']);
      }

      // Input parameter: ids
      if (isset($query_options['mlt']['ids'])) {
        $mlt_query['more_like_this']['ids'] = $query_options['mlt']['ids'];
      }

      // Input parameter: like
      if (isset($query_options['mlt']['like'])) {
        $mlt_query['more_like_this']['like'] = $query_options['mlt']['like'];
      }

      // Input parameter: unlike
      if (isset($query_options['mlt']['unlike'])) {
        $mlt_query['more_like_this']['unlike'] = $query_options['mlt']['unlike'];
      }

      // Input parameter: fields
      $mlt_query['more_like_this']['fields'] = array_values(
        $query_options['mlt']['fields']
      );
      // TODO: Make this settings configurable in the view.
      $mlt_query['more_like_this']['max_query_terms'] = 1;
      $mlt_query['more_like_this']['min_doc_freq'] = 1;
      $mlt_query['more_like_this']['min_term_freq'] = 1;

      $this->body['query']['bool']['must'][] = $mlt_query;
    }
  }

}
