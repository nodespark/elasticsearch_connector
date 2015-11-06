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
class DESConnector implements DESConnectorInterface {

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
    if (ELASTICSEARCH_CONNECTOR_VERSION < 2) {
      return DESConnector81::getInstance($hosts);
    }
    else {
      return DESConnector82::getInstance($hosts);
    }
  }

  /**
   * @return mixed
   */
  public function getCluster() {
    return $this->getClient()->cluster();
  }

  /**
   * @return mixed
   */
  public function getNodes() {
    return $this->getClient()->nodes();
  }

  /**
   * @return mixed
   */
  protected function getClient() {
    return $this->client;
  }

  /**
   * Check if we have a connection the cluster.
   *
   * @return bool
   */
  public function getClusterStatus() {
    try {
      $health = $this->getCluster()->health();
      return $health['status'];
    }
    catch (Exception $e) {
      return FALSE;
    }
  }

  /**
   * Check if we have a connection the cluster.
   *
   * @return bool
   */
  public function clusterIsOk() {
    try {
      $health = $this->getCluster()->health();
      if (in_array($health['status'], array(self::CLUSTER_STATUS_GREEN, self::CLUSTER_STATUS_YELLOW))) {
        $status = TRUE;
      }
      else {
        $status = FALSE;
      }
    }
    catch (Exception $e) {
      $status = FALSE;
    }

    return $status;
  }

  /**
   * Get cluster health.
   *
   * @return array
   */
  public function getClusterHealth() {
    return $this->getCluster()->health();
  }

  /**
   * Get cluster state.
   *
   * @return array
   */
  public function getClusterState() {
    return $this->getCluster()->state();
  }

  /**
   * Get cluster stats.
   *
   * @return array
   */
  public function getClusterStats() {
    return $this->getCluster()->stats();
  }

  /**
   * Return cluster info.
   *
   * @return array
   *   Info array.
   *
   * @throws \Exception
   *   Exception().
   */
  public function getClusterInfo() {
    $result = FALSE;

    try {
      try {
        $result['state'] = $this->getClusterState();
        $result['health'] = $this->getClusterHealth();
        $result['stats'] = $this->getClusterStats();
      }
      catch (\Exception $e) {
        // TODO: Do not set messages or log messages into the abstraction.
        drupal_set_message($e->getMessage(), 'error');
      }
    }
    catch (\Exception $e) {
      throw $e;
    }

    return $result;
  }

  /**
   * Get nodes stats.
   *
   * @return array
   */
  public function getNodesStats() {
    return $this->getNodes()->stats();
  }

  /**
   * Get nodes info.
   *
   * @return array
   */
  public function getNodesInfo() {
    return $this->getNodes()->info();
  }

  /**
   * Return nodes info.
   *
   * @return array
   *   Info array.
   *
   * @throws \Exception
   *   Exception().
   */
  public function getNodesProperties() {
    $result = FALSE;

    try {
      try {
        $result['stats'] = $this->getNodesStats();
        $result['info'] = $this->getNodesInfo();
      }
      catch (\Exception $e) {
        // TODO: Do not set messages or log messages into the abstraction.
        drupal_set_message($e->getMessage(), 'error');
      }
    }
    catch (\Exception $e) {
      throw $e;
    }

    return $result;
  }

  /**
   * Elasticsearch info function.
   *
   * @return array
   */
  public function info() {
    return $this->getClient()->info();
  }

  /**
   * Elasticsearch ping function.
   *
   * @return array
   */
  public function ping() {
    return $this->getClient()->ping();
  }
}
