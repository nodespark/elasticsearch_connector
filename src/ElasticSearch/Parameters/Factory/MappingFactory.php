<?php

namespace Drupal\elasticsearch_connector\ElasticSearch\Parameters\Factory;

use Drupal\search_api\Item\FieldInterface;
use Elasticsearch\Common\Exceptions\ElasticsearchException;
use Drupal\elasticsearch_connector\Event\PrepareMappingEvent;

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
    $mappingConfig = NULL;

    switch ($type) {
      case 'text':
        $mappingConfig = [
          'type' => 'text',
          'boost' => $field->getBoost(),
          'fields' => [
            "keyword" => [
              "type" => 'keyword',
              'ignore_above' => 256,
            ]
          ]
        ];
        break;

      case 'uri':
      case 'string':
      case 'token':
        $mappingConfig = [
          'type' => 'keyword',
        ];
        break;

      case 'integer':
      case 'duration':
        $mappingConfig = [
          'type' => 'integer',
        ];
        break;

      case 'boolean':
        $mappingConfig = [
          'type' => 'boolean',
        ];
        break;

      case 'decimal':
        $mappingConfig = [
          'type' => 'float',
        ];
        break;

      case 'date':
        $mappingConfig = [
          'type' => 'date',
          'format' => 'strict_date_optional_time||epoch_second',
        ];
        break;

      case 'attachment':
        $mappingConfig = [
          'type' => 'attachment',
        ];
        break;

      case 'object':
        $mappingConfig = [
          'type' => 'nested'
        ];
        break;
    }

    // Allow other modules to alter mapping config before we create it.
    $dispatcher = \Drupal::service('event_dispatcher');
    $prepareMappingEvent = new PrepareMappingEvent($mappingConfig, $type, $field);
    $event = $dispatcher->dispatch(PrepareMappingEvent::PREPARE_MAPPING, $prepareMappingEvent);
    $mappingConfig = $event->getMappingConfig();

    return $mappingConfig;
  }

}
