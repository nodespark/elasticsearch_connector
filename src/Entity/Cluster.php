<?php

/**
 * @file
 * Contains \Drupal\elasticsearch_connector\Entity\Cluster.
 */

namespace Drupal\elasticsearch_connector\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Entity;

/**
 * Defines the search server configuration entity.
 *
 * @ConfigEntityType(
 *   id = "elasticsearch_cluster",
 *   label = @Translation("Elasticsearch Cluster"),
 *   handlers = {
 *     "storage" = "Drupal\Core\Config\Entity\ConfigEntityStorage",
 *     "list_builder" = "Drupal\elasticsearch_connector\Controller\ClusterListBuilder",
 *     "form" = {
 *       "default" = "Drupal\elasticsearch_connector\Form\ClusterForm",
 *       "edit" = "Drupal\elasticsearch_connector\Form\ClusterForm",
 *       "delete" = "Drupal\elasticsearch_connector\Form\ClusterDeleteForm",
 *     },
 *   },
 *   admin_permission = "administer elasticsearch",
 *   config_prefix = "cluster",
 *   entity_keys = {
 *     "id" = "cluster_id",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "url" = "url",
 *     "status" = "status"
 *   },
 *   links = {
 *     "canonical" = "/admin/config/search/elasticsearch-connector/clusters/{elasticsearch_cluster}",
 *     "add-form" = "/admin/config/search/elasticsearch-connector/clusters/add",
 *     "edit-form" = "/admin/config/search/elasticsearch-connector/clusters/{elasticsearch_cluster}/edit",
 *     "delete-form" = "/admin/config/search/elasticsearch-connector/clusters/{elasticsearch_cluster}/delete",
 *   }
 * )
 */
class Cluster extends ConfigEntityBase {

  // Active status
  const ELASTICSEARCH_CONNECTOR_STATUS_ACTIVE = 1;

  // Inactive status
  const ELASTICSEARCH_CONNECTOR_STATUS_INACTIVE = 0;

  // Default connection timeout in seconds.
  const ELASTICSEARCH_CONNECTOR_DEFAULT_TIMEOUT = 3;
  /**
   * The cluster machine name.
   *
   * @var string
   */
  public $cluster_id;

  /**
   * The human-readable name of the cluster entity.
   *
   * @var string
   */
  public $name;

  /**
   * Status.
   *
   * @var string
   */
  public $status;

  /**
   * The cluster url.
   *
   * @var string
   */
  public $url;

  /**
   * Options of the cluster.
   *
   * @var array
   */
  public $options;

  /**
   * The locked status of this cluster.
   *
   * @var bool
   */
  protected $locked = FALSE;

  /**
   * The connector class.
   *
   * @var string
   */
  protected $connector = 'Drupal\elasticsearch_connector\DESConnector\DESConnector';

  /**
   * {@inheritdoc}
   */
  public function id() {
    return isset($this->cluster_id) ? $this->cluster_id : NULL;
  }

  /**
   * Get the default (cluster) used for elasticsearch.
   *
   * @return string
   */
  public static function getDefaultCluster() {
    return \Drupal::state()->get('elasticsearch_connector_get_default_connector', '');
  }

  /**
   * Set the default (cluster) used for elasticsearch.
   *
   * @return string
   */
  public static function setDefaultCluster($cluster_id) {
    return \Drupal::state()->set('elasticsearch_connector_get_default_connector', $cluster_id);
  }

  /**
   * Get the Elasticsearch client.
   *
   * @param object $cluster
   *   The cluster object.
   *
   * @return object
   *   The Elasticsearch object.
   */
  public static function getClientInstance($cluster) {
    $hosts = array(
      array(
        'url' => $cluster->url,
        'options' => $cluster->options,
      ),
    );

    $client = call_user_func($cluster->connector . '::getInstance', $hosts);
    return $client;
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
    try {
      $client = self::getClientInstance($this);
      $result = $client->getClusterInfo();
    }
    catch (\Exception $e) {
      throw $e;
    }

    return $result;
  }

  /**
   * Return the cluster object based on Cluster ID.
   *
   * @param string $cluster_id
   *
   * @return \Elasticsearch\Client
   */
  protected function getClientById($cluster_id) {
    $client = NULL;

    $default_cluster = self::getDefaultCluster();
    if (!isset($cluster_id) && !empty($default_cluster)) {
      $cluster_id = $default_cluster;
    }

    if (!empty($cluster_id)) {
      $client = FALSE;
      $cluster = self::load($cluster_id);
      if ($cluster) {
        $client = $this->getClientInstance($cluster);
      }
    }

    return $client;
  }

  /**
   * Load all clusters.
   *
   * @param bool $include_inactive
   *
   * @return \Drupal\elasticsearch_connector\Entity\Cluster[]
   */
  public static function loadAllClusters($include_inactive = TRUE) {
    $clusters = self::loadMultiple();
    foreach ($clusters as $cluster) {
      if (!$include_inactive && !$cluster->status) {
        unset($clusters[$cluster->cluster_id]);
      }
    }

    return $clusters;
  }

  /**
   * Check if the REST response is successful and with status code 200.
   *
   * @param mixed $response
   *
   * @return bool
   */
  public static function elasticsearchCheckResponseAck($response) {
    if (is_array($response) && !empty($response['acknowledged'])) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Check if the cluster status is OK.
   *
   * @return bool
   */
  public function checkClusterStatus() {
    // TODO: Check if we can initialize the client in __construct().
    $client = self::getClientInstance($this);
    return $client->clusterIsOk();
  }

}
