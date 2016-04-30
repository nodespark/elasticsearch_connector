<?php

namespace Drupal\elasticsearch_connector\ElasticSearch\Parameters\Builder;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\elasticsearch_connector\ElasticSearch\Parameters;
use Drupal\elasticsearch_connector\ElasticSearch\Parameters\Factory\IndexFactory;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Query\Condition;
use Drupal\search_api\Query\ConditionGroupInterface;
use Drupal\search_api\Query\QueryInterface;
use Elasticsearch\Common\Exceptions\ElasticsearchException;

/**
 * Class SearchBuilder
 */
class SearchBuilder {

  use StringTranslationTrait;

  /**
   * @var Index
   */
  protected $index;

  /**
   * @var \Drupal\search_api\Query\QueryInterface
   */
  protected $query;

  /**
   * ParameterBuilder constructor.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   */
  public function __construct(QueryInterface $query) {
    $this->query = $query;
    $this->index = $query->getIndex();
  }

  /**
   * @return array
   */
  public function build() {

    // Query options.
    $params = IndexFactory::index($this->index, TRUE);
    $query_options = $this->getSearchQueryOptions();

    // Main query.
    $body = &$params['body'];

    // Set the size and from parameters.
    $body['from'] = $query_options['query_offset'];
    $body['size'] = $query_options['query_limit'];

    // Sort
    if (!empty($query_options['sort'])) {
      $body['sort'] = $query_options['sort'];
    }

    $body['fields'] = [];
    $fields = &$body['fields'];

    // More Like This
    if (!empty($query_options['mlt'])) {
      $mlt_query['more_like_this'] = [];
      $mlt_query['more_like_this']['like_text'] = $query_options['mlt']['id'];
      $mlt_query['more_like_this']['fields'] = array_values(
        $query_options['mlt']['fields']
      );
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
        $body['query'] = [];
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
      $query_body['match_all'] = [];
    }

    // Preserve the options for futher manipulation if necessary.
    $this->query->setOption('ElasticParams', $params);

    return $params;
  }


  /**
   * Helper function return associative array with query options.
   *
   * @return array
   */
  protected function getSearchQueryOptions() {

    // Query options.
    $query_options = $this->query->getOptions();

    // Index fields.
    $index_fields = $this->index->getFields();

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
      $query_full_text_fields = $this->index->getFulltextFields();
      $query_fields = array();
      foreach ($query_full_text_fields as $full_text_field_name) {
        $full_text_field = $index_fields[$full_text_field_name];
        $query_fields[] = $full_text_field->getFieldIdentifier(
          ) . '^' . $full_text_field->getBoost();
      }

      // Query string.
      $search_string = $this->flattenKeys($keys, $query_options['parse mode']);

      if (!empty($search_string)) {
        $query_search_string = ['query_string' => []];
        $query_search_string['query_string']['query'] = $search_string;
        $query_search_string['query_string']['fields'] = $query_fields;
        $query_search_string['query_string']['analyzer'] = 'snowball';
        $query_search_string['query_string']['default_operator'] = 'OR';
      }
    }

    $sort = NULL;
    // Sort.
    try {
      $sort = $this->getSortSearchQuery();
    } catch (ElasticsearchException $e) {
      watchdog_exception('Elasticsearch Search API', $e);
      drupal_set_message($e->getMessage(), 'error');
    }

    // Filters.
    try {
      $parsed_query_filters = $this->getQueryFilters(
        $this->query->getConditionGroup(),
        $index_fields
      );
      if (!empty($parsed_query_filters)) {
        $query_search_filter = $parsed_query_filters[0];
      }
    } catch (ElasticsearchException $e) {
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

    return [
      'query_offset' => $query_offset,
      'query_limit' => $query_limit,
      'query_search_string' => $query_search_string,
      'query_search_filter' => $query_search_filter,
      'sort' => $sort,
      'mlt' => $mlt,
    ];
  }


  /**
   * Return a full text search query.
   *
   * TODO: better handling of parse modes.
   *
   * @param array  $keys
   * @param string $parse_mode
   * @param array  $full_text_fields
   *
   * @return string
   */
  protected function flattenKeys(
    array $keys,
    $parse_mode = '',
    $full_text_fields = []
  ) {
    $conjunction = isset($keys['#conjunction']) ? $keys['#conjunction'] : 'AND';
    $negation = !empty($keys['#negation']);
    $values = [];

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
      return ($negation === TRUE ? 'NOT ' : '') . '(' . implode(
        " {$conjunction} ",
        $values
      ) . ')';
    }
    else {
      return '';
    }
  }

  /**
   * Helper function that return Sort for query in search.
   *
   * @return array
   * @throws \Exception
   */
  protected function getSortSearchQuery() {

    $index_fields = $this->index->getFields();
    $sort = [];
    foreach ($this->query->getSorts() as $field_id => $direction) {
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
        // TODO: no silly exceptions...
        throw new \Exception(t('Incorrect sorting!.'));
      }
    }
    return $sort;
  }

  /**
   * Recursively parse Search API condition group.
   *
   * @param ConditionGroupInterface $condition_group
   * @param array                   $index_fields
   * @param string                  $ignored_field_id
   *
   * @return array|null
   * @throws \Exception
   */
  protected function getQueryFilters(
    ConditionGroupInterface $condition_group,
    array $index_fields,
    $ignored_field_id = ''
  ) {

    if (empty($condition_group)) {
      return NULL;
    }
    else {
      $conjunction = $condition_group->getConjunction();

      $filters = [];

      foreach ($condition_group->getConditions() as $condition) {
        $filter = NULL;

        // Simple filter [field_id, value, operator].
        if ($condition instanceof Condition) {

          if (!$condition->getField() || !$condition->getValue(
            ) || !$condition->getOperator()
          ) {
            // TODO: When using views the sort field is coming as a filter and messing with this section.
            // throw new Exception(t('Incorrect filter criteria is using for searching!'));
          }

          $field_id = $condition->getField();
          if (!isset($index_fields[$field_id])) {
            // TODO: propper exception
            throw new \Exception(
              t(
                ':field_id Undefined field ! Incorrect filter criteria is using for searching!',
                [':field_id' => $field_id]
              )
            );
          }

          // Check operator.
          if (!$condition->getOperator()) {
            // TODO: propper exception
            throw new \Exception(
              t(
                'Empty filter operator for :field_id field! Incorrect filter criteria is using for searching!',
                [':field_id' => $field_id]
              )
            );
          }

          // Check field.
          $filter = Parameters::filterFromCondition($condition);

          if (!empty($filter)) {
            $filters[] = $filter;
          }
        }
        // Nested filters.
        elseif ($condition instanceof ConditionGroupInterface) {
          $nested_filters = $this->getQueryFilters(
            $condition,
            $index_fields,
            $ignored_field_id
          );

          if (!empty($nested_filters)) {
            $filters = array_merge($filters, $nested_filters);
          }
        }
      }

      $filters = $this->setFiltersConjunction($filters, $conjunction);

      return $filters;
    }
  }


  /**
   * Helper function thaht set filters conjunction
   *
   * @param array  $filters
   * @param string $conjunction
   *
   * @return array|null
   * @throws \Exception
   */
  protected function setFiltersConjunction(array &$filters, $conjunction) {
    if (count($filters) > 1) {
      if ($conjunction === 'OR') {
        $filters = [['or' => $filters]];
      }
      elseif ($conjunction === 'AND') {
        $filters = [['and' => $filters]];
      }
      else {
        throw new \Exception(
          t(
            'Undefined conjunction :conjunction! Available values are :avail_conjunction! Incorrect filter criteria is using for searching!',
            [
              ':conjunction!' => $conjunction,
              ':avail_conjunction' => $conjunction
            ]
          )
        );
      }
    }

    return $filters;
  }
}
