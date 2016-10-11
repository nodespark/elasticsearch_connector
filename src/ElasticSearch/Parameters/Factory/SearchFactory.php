<?php

namespace Drupal\elasticsearch_connector\ElasticSearch\Parameters\Factory;

use Drupal\elasticsearch_connector\ElasticSearch\Parameters\Builder\SearchBuilder;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Utility\Utility as SearchApiUtility;

/**
 * Class SearchFactory.
 */
class SearchFactory {

  /**
   * Build search parameters from a query interface.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *
   * @return array
   */
  public static function search(QueryInterface $query) {
    $builder = new SearchBuilder($query);

    return $builder->build();
  }

  /**
   * Parse a elastic search response into a ResultSetInterface
   * TODO: Add excerpt handling.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   * @param array $response
   *
   * @return \Drupal\search_api\Query\ResultSetInterface
   */
  public static function parseResult(QueryInterface $query, array $response) {
    $index = $query->getIndex();

    // Set up the results array.
    $results = $query->getResults();
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
            $elasticsearch_property = [$elasticsearch_property];
          }
          $field = $index->getField($elasticsearch_property_id);
          if (!$field instanceof FieldInterface) {
            $field = SearchApiUtility::createField(
              $index,
              $elasticsearch_property_id,
              [
                'property_path' => $elasticsearch_property_id,
              ]
            );
          }
          $field->setValues($elasticsearch_property);
          $result_item->setField($elasticsearch_property_id, $field);
        }

        $results->addResultItem($result_item);
      }
    }

    return $results;
  }

}
