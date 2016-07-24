<?php

namespace Drupal\elasticsearch_connector\ElasticSearch\Parameters\Factory;

use Drupal\search_api\Item\FieldInterface;
use Elasticsearch\Common\Exceptions\ElasticsearchException;

/**
 * Class MappingFactory
 */
class MappingFactory {

  /**
   * Helper function. Get the elasticsearch mapping for a field.
   *
   * @param FieldInterface $field
   *
   * @return array|null
   */
  public static function mappingFromField(FieldInterface $field) {
    try {
      $type = $field->getType();

      switch ($type) {
        case 'text':
          return [
            'type' => 'string',
            'boost' => $field->getBoost(),
            'analyzer' => 'snowball',
          ];

        case 'uri':
        case 'string':
        case 'token':
          return [
            'type' => 'string',
            'index' => 'not_analyzed',
          ];

        case 'integer':
        case 'duration':
          return [
            'type' => 'integer',
          ];

        case 'boolean':
          return [
            'type' => 'boolean',
          ];

        case 'decimal':
          return [
            'type' => 'float',
          ];

        case 'date':
          return [
            'type' => 'date',
            'format' => 'epoch_second',
          ];
      }
    }
    catch (ElasticsearchException $e) {
      watchdog_exception('Elasticsearch Backend', $e);
    }

    return NULL;
  }

}
