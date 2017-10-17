<?php

namespace Drupal\elasticsearch_connector\ElasticSearch\Parameters\Factory;

use Drupal\search_api\Item\FieldInterface;
use Elasticsearch\Common\Exceptions\ElasticsearchException;

/**
 * Class MappingFactory.
 */
class MappingFactory {

  /**
   * Helper function. Get the elasticsearch mapping for a field.
   *
   * @param FieldInterface $field
   *
   * @return array|null
   *   Array of settings when a known field type is provided. Null otherwise.
   */
  public static function mappingFromField(FieldInterface $field) {
    $type = $field->getType();

    switch ($type) {
      case 'text':
        return [
          'type' => 'text',
          'boost' => $field->getBoost(),
          'fields' => [
            "keyword" => [
              "type" => 'keyword',
              'ignore_above' => 256,
            ]
          ]
        ];

      case 'uri':
      case 'string':
      case 'token':
        return [
          'type' => 'keyword',
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

      case 'attachment':
        return [
          'type' => 'attachment',
        ];

      case 'object':
        return [
          'type' => 'nested'
        ];
    }

    return NULL;
  }

}
