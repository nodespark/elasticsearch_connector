<?php

namespace Drupal\elasticsearch_connector\ElasticSearch\Parameters\Factory;

use Drupal\search_api\IndexInterface;

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
   * @param bool $with_type
   *   Should the index be created with a type.
   *
   * @return array
   *   Associative array with the following keys:
   *   - index: The name of the index on the Elasticsearch server.
   *   - type(optional): The name of the type to use for the given index.
   */
  public static function index(IndexInterface $index, $with_type = FALSE) {
    $params = [];
    $params['index'] = IndexFactory::getIndexName($index);

    if ($with_type) {
      $params['type'] = $index->id();
    }

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
     $indexName = IndexFactory::getIndexName($index);
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
    $params = IndexFactory::index($index, TRUE);
    foreach ($ids as $id) {
      $params['body'][] = [
        'delete' => [
          '_index' => $params['index'],
          '_type' => $params['type'],
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
    $params = IndexFactory::index($index, TRUE);

    foreach ($items as $id => $item) {
      $data = [];
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
    $params = IndexFactory::index($index, TRUE);

    $properties = [
      'id' => [
        'type' => 'string',
        'index' => 'not_analyzed',
        'include_in_all' => FALSE,
      ],
    ];

    // Map index fields.
    foreach ($index->getFields() as $field_id => $field_data) {
      $properties[$field_id] = MappingFactory::mappingFromField($field_data);
    }

    $params['body'][$params['type']]['properties'] = $properties;

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
