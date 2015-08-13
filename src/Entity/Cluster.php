<?php

/**
 * @file
 * Contains \Drupal\elasticsearch_connector\Entity\Cluster.
 */

namespace Drupal\elasticsearch_connector\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Elasticsearch\Client;

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
 *     "url" = "url",
 *     "status" = "status"
 *   },
 *   links = {
 *     "canonical" = "elasticsearch.canonical",
 *     "info" = "elasticsearch.cluster_info",
 *     "add-form" = "elasticsearch.cluster_add",
 *     "edit-form" = "elasticsearch.cluster_edit",
 *     "delete-form" = "elasticsearch.cluster_delete",
 *   }
 * )
 */
class Cluster extends ConfigEntityBase {

  // Active status
  const ELASTICSEARCH_STATUS_ACTIVE = 1;

  // Inactive status
  const ELASTICSEARCH_STATUS_INACTIVE = 0;

  // Cluster status
  const ELASTICSEARCH_CLUSTER_STATUS_OK = 200;

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
   * @var array
   */
  public $options;

  /**
   * The locked status of this cluster.
   * @var bool
   */
  protected $locked = FALSE;

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
   * Return cluster info.
   * @return array
   */
  public static function getClusterInfo($cluster) {
    $result = FALSE;

    try {
      $client = self::getClientByUrls(array($cluster->url));
      if (!empty($client)) {
        try {
          $info = $client->info();
          $result['client'] = $client;
          $result['info'] = $result['state'] = $result['health'] = $result['stats'] = array();
          if (self::checkClusterStatus($info)) {
            $result['info'] = $info;
            $result['state'] = $client->cluster()->state();
            $result['health'] = $client->cluster()->health();
            $result['stats'] = $client->nodes()->stats();
          }
        }
        catch (\Exception $e) {
          drupal_set_message($e->getMessage(), 'error');
        }
      }
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
   * @return \Elasticsearch\Client
   */
  protected function getClientById($cluster_id) {
    $client = NULL;

    $default_cluster = $this::getDefaultCluster();
    if (!isset($cluster_id) && !empty($default_cluster)) {
      $cluster_id = $this::getDefaultCluster();
    }

    if (!empty($cluster_id)) {
      $client = FALSE;
      $cluster = $this::loadCluster($cluster_id);
      if ($cluster) {
        $client = $this::getClientByUrls($cluster->url);
      }
    }

    return $client;
  }

  /**
   * Load a cluster object
   *
   * @param $cluster_id
   * @return \Drupal\elasticsearch_connector\Entity\Cluster
   */
  public static function loadCluster($cluster_id) {
    return entity_load('elasticsearch_cluster', $cluster_id);
  }

  /**
   * Load all clusters
   *
   * @param bool $include_inactive
   * @return \Drupal\elasticsearch_connector\Entity\Cluster[]
   */
  public static function loadAllClusters($include_inactive = TRUE) {
    $clusters = entity_load_multiple('elasticsearch_cluster');
    foreach ($clusters as $cluster) {
      if (!$include_inactive && !$cluster->status) {
        unset($clusters[$cluster->cluster_id]);
      }
    }
    return $clusters;
  }

  /**
   * We need to handle the case where url is and array of urls
   *
   * @param string $url
   * @return Client
   */
  public static function getClientByUrls($urls) {
    $options = array(
      'hosts' => $urls,
    );

    \Drupal::moduleHandler()->alter('elasticsearch_connector_load_library_options', $options);
    return new Client($options);
  }

/**
 * Check if the REST response is successful and with status code 200.
 * @param array $response
 *
 * @return boolean
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
   * @param array $status
   * @return bool
   */
    public static function checkClusterStatus($status) {
    if (is_array($status) && $status['status'] == ELASTICSEARCH_CLUSTER_STATUS_OK) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

}
