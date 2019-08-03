<?php

namespace Drupal\elasticsearch_connector\ElasticSearch\Parameters\Factory;

use Drupal\search_api\IndexInterface;
use Drupal\elasticsearch_connector\Event\PrepareIndexEvent;
use Drupal\elasticsearch_connector\Event\PrepareIndexMappingEvent;
use Drupal\elasticsearch_connector\Event\BuildIndexParamsEvent;
use Drupal\search_api_autocomplete\Suggester\SuggesterInterface;

/**
 * Create Elasticsearch Indices.
 */
class IndexFactory {

  /**
   * Build parameters required to index.
   *
   * TODO: We need to handle the following params as well:
   * ['consistency'] = (enum) Explicit write consistency setting for the
   * operation
   * ['refresh']     = (boolean) Refresh the index after performing the
   * operation
   * ['replication'] = (enum) Explicitly set the replication type
   * ['fields']      = (list) Default comma-separated list of fields to return
   * in the response for updates.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   Index to create.
   *
   * @return array
   *   Associative array with the following keys:
   *   - index: The name of the index on the Elasticsearch server.
   *   - type(optional): The name of the type to use for the given index.
   */
  public static function index(IndexInterface $index) {
    $params = [];
    $params['index'] = static::getIndexName($index);
    return $params;
  }

  /**
   * Build parameters required to create an index
   * TODO: Add the timeout option.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *
   * @return array
   */
   public static function create(IndexInterface $index) {
     $indexName = static::getIndexName($index);
     $indexConfig =  [
       'index' => $indexName,
       'body' => [
         'settings' => [
           'number_of_shards' => $index->getOption('number_of_shards', 5),
           'number_of_replicas' => $index->getOption('number_of_replicas', 1),
         ],
       ],
     ];

     // Allow other modules to alter index config before we create it.
     $dispatcher = \Drupal::service('event_dispatcher');
     $prepareIndexEvent = new PrepareIndexEvent($indexConfig, $indexName);
     $event = $dispatcher->dispatch(PrepareIndexEvent::PREPARE_INDEX, $prepareIndexEvent);
     $indexConfig = $event->getIndexConfig();

     return $indexConfig;
   }

  /**
   * Build parameters to bulk delete indexes.
   *
   * @param \Drupal\search_api\IndexInterface $index
   * @param array $ids
   *
   * @return array
   */
  public static function bulkDelete(IndexInterface $index, array $ids) {
    $params = IndexFactory::index($index);
    foreach ($ids as $id) {
      $params['body'][] = [
        'delete' => [
          '_index' => $params['index'],
          '_id' => $id,
        ],
      ];
    }

    return $params;
  }

  /**
   * Build parameters to bulk delete indexes.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   Index object.
   * @param \Drupal\search_api\Item\ItemInterface[] $items
   *   An array of items to be indexed, keyed by their item IDs.
   *
   * @return array
   *   Array of parameters to send along to Elasticsearch to perform the bulk
   *   index.
   */
  public static function bulkIndex(IndexInterface $index, array $items) {
    $params = static::index($index);

    foreach ($items as $id => $item) {
      $data = [
        '_language' => $item->getLanguage(),
      ];
      /** @var \Drupal\search_api\Item\FieldInterface $field */
      foreach ($item as $name => $field) {
        $field_type = $field->getType();
        if (!empty($field->getValues())) {
          $values = array();
          foreach ($field->getValues() as $value) {
            switch ($field_type) {
              case 'string':
                $values[] = (string) $value;
                break;

              case 'text':
                $values[] = $value->toText();
                break;

              case 'boolean':
                $values[] = (boolean) $value;
                break;

              default:
                $values[] = $value;
            }
          }
          $data[$field->getFieldIdentifier()] = $values;
        }
      }
      $params['body'][] = ['index' => ['_id' => $id]];
      $params['body'][] = $data;
    }

    // Allow other modules to alter index params before we send them.
    $indexName = IndexFactory::getIndexName($index);
    $dispatcher = \Drupal::service('event_dispatcher');
    $buildIndexParamsEvent = new BuildIndexParamsEvent($params, $indexName);
    $event = $dispatcher->dispatch(BuildIndexParamsEvent::BUILD_PARAMS, $buildIndexParamsEvent);
    $params = $event->getElasticIndexParams();

    return $params;
  }

  /**
   * Build parameters required to create an index mapping.
   *
   * TODO: We need also:
   * $params['index'] - (Required)
   * ['type'] - The name of the document type
   * ['timeout'] - (time) Explicit operation timeout.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   Index object.
   *
   * @return array
   *   Parameters required to create an index mapping.
   */
  public static function mapping(IndexInterface $index) {
    $params = static::index($index);

    $properties = [
      'id' => [
        'type' => 'keyword',
        'index' => 'true',
      ],
    ];

    // Figure out which fields are used for autocompletion if any.
    if (\Drupal::moduleHandler()->moduleExists('search_api_autocomplete')) {
      $autocompletes = \Drupal::entityTypeManager()->getStorage('search_api_autocomplete_search')->loadMultiple();
      $all_autocompletion_fields = [];
      foreach ($autocompletes as $autocomplete) {
        $suggester = \Drupal::service('plugin.manager.search_api_autocomplete.suggester');
        $plugin = $suggester->createInstance('server', ['#search' => $autocomplete]);
        assert($plugin instanceof SuggesterInterface);
        $configuration = $plugin->getConfiguration();
        $autocompletion_fields = isset($configuration['fields']) ? $configuration['fields'] : [];
        if (!$autocompletion_fields) {
          $autocompletion_fields = $plugin->getSearch()->getIndex()->getFulltextFields();
        }

        // Collect autocompletion fields in an array keyed by field id.
        $all_autocompletion_fields += array_flip($autocompletion_fields);
      }
     }

    // Map index fields.
    foreach ($index->getFields() as $field_id => $field_data) {
      $properties[$field_id] = MappingFactory::mappingFromField($field_data);
      // Enable fielddata for fields that are used with autocompletion.
      if (isset($all_autocompletion_fields[$field_id])) {
        $properties[$field_id]['fielddata'] = TRUE;
      }
    }

    $properties['_language'] = [
      'type' => 'keyword',
    ];

    $params['body']['properties'] = $properties;

    // Allow other modules to alter index mapping before we create it.
    $dispatcher = \Drupal::service('event_dispatcher');
    $prepareIndexMappingEvent = new PrepareIndexMappingEvent($params, $params['index']);
    $event = $dispatcher->dispatch(PrepareIndexMappingEvent::PREPARE_INDEX_MAPPING, $prepareIndexMappingEvent);
    $params = $event->getIndexMappingParams();

    return $params;
  }

  /**
   * Helper function. Returns the Elasticsearch name of an index.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   Index object.
   *
   * @return string
   *   The name of the index on the Elasticsearch server. Includes a prefix for
   *   uniqueness, the database name, and index machine name.
   */
  public static function getIndexName(IndexInterface $index) {

    $options = \Drupal::database()->getConnectionOptions();
    $site_database = $options['database'];

    $index_machine_name = is_string($index) ? $index : $index->id();

    return strtolower(preg_replace(
      '/[^A-Za-z0-9_]+/',
      '',
      'elasticsearch_index_' . $site_database . '_' . $index_machine_name
    ));
  }

}
