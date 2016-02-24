<?php

namespace Drupal\elasticsearch_connector\ElasticSearch;

use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\ElasticsearchException;

/**
 * Class ClientConnector
 *
 * @author andy.thorne@timeinc.com
 */
class ClientConnector {

  const CLUSTER_STATUS_GREEN = 'green';
  const CLUSTER_STATUS_YELLOW = 'yellow';
  const CLUSTER_STATUS_RED = 'red';

  /**
   * @var Client
   */
  protected $client;

  /**
   * ClientConnector constructor.
   *
   * @param Client $client
   */
  public function __construct(Client $client) {
    $this->client = $client;
  }

  /**
   * {@inheritdoc}
   */
  public function getClusterStatus() {
    try {
      $health = $this->client->cluster()->health();
      return $health['status'];
    } catch (ElasticsearchException $e) {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isClusterOk() {
    try {
      $health = $this->client->cluster()->health();
      if (in_array(
        $health['status'],
        [self::CLUSTER_STATUS_GREEN, self::CLUSTER_STATUS_YELLOW]
      )) {
        $status = TRUE;
      }
      else {
        $status = FALSE;
      }
    } catch (ElasticsearchException $e) {
      $status = FALSE;
    }
    return $status;
  }

  /**
   * {@inheritdoc}
   */
  public function getClusterInfo() {
    $result = [
      'state' => NULL,
      'health' => NULL,
      'stats' => NULL,
    ];

    try {
      $result['state'] = $this->client->cluster()->State();
    } catch (ElasticsearchException $e) {
      drupal_set_message($e->getMessage(), 'error');
    }

    try {
      $result['health'] = $this->client->cluster()->Health();
    } catch (ElasticsearchException $e) {
      drupal_set_message($e->getMessage(), 'error');
    }

    try {
      $result['stats'] = $this->client->cluster()->Stats();
    } catch (ElasticsearchException $e) {
      drupal_set_message($e->getMessage(), 'error');
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getNodesProperties() {
    $result = FALSE;
    try {
      $result['stats'] = $this->client->nodes()->stats();
      $result['info'] = $this->client->nodes()->info();
    } catch (ElasticsearchException $e) {
      drupal_set_message($e->getMessage(), 'error');
    }

    return $result;
  }
}
