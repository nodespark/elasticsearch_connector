<?php
/**
 * @file
 * Provides Elasticsearch Client for Drupal's Elasticsearch Connector module.
 */

// TODO: Move all public methods from DESConnector to DESConnectorInterface.
// TODO: We need to implement __call() method to directly call Elasticsearch
// client if missing.
namespace Drupal\elasticsearch_connector\DESConnector;

use Elasticsearch\ClientBuilder;
use Masterminds\HTML5\Exception;

/**
 * Drupal Elasticsearch Interface.
 *
 * @package Drupal\elasticsearch_connector
 */
class DESConnector82 extends DESConnector implements DESConnectorInterface {

  const CLUSTER_STATUS_GREEN = 'green';

  const CLUSTER_STATUS_YELLOW = 'yellow';

  const CLUSTER_STATUS_RED = 'red';

  protected static $instances;

  protected $client;

  /**
   * Singleton constructor.
   */
  private function __construct($client) {
    // TODO: Validate if we have a valid client.
    $this->client = $client;
  }

  /**
   * Singleton clone.
   */
  private function __clone() {}

  /**
   * Singleton wakeup.
   */
  private function __wakeup() {}

  /**
   * Singleton sleep.
   */
  private function __sleep() {}

  /**
   * Initializes the needed client.
   *
   * TODO: We need to check the available options for the ClientBuilder
   *       and set them after the alter hook.
   *
   * @param array $hosts
   *   The URLs of the Elasticsearch hosts.
   *
   * @return Client
   */
  public static function getInstance(array $hosts) {
    $hash = md5(implode(':', $hosts));

    if (!isset($instances[$hash])) {
      $options = array(
        'hosts' => $hosts,
      );

      // TODO: Remove this from the abstraction!
      // It should be passed via parameter.
      \Drupal::moduleHandler()
        ->alter('elasticsearch_connector_load_library_options', $options);

      $builder = ClientBuilder::create();
      $builder->setHosts($options['hosts']);

      $instances[$hash] = new DESConnector82($builder->build());
    }

    return $instances[$hash];
  }

}
