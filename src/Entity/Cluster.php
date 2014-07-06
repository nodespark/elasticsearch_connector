<?php

namespace Drupal\elasticsearch\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Elasticsearch\Client;
use Drupal\Component\Utility\UrlHelper;

/**
 * Defines the search server configuration entity.
 *
 * @ConfigEntityType(
 *   id = "elasticsearch_cluster",
 *   label = @Translation("Elasticsearch Cluster"),
 *   controllers = {
 *     "storage" = "Drupal\Core\Config\Entity\ConfigEntityStorage",
 *     "list_builder" = "Drupal\Core\Config\Entity\ConfigEntityListBuilder",
 *     "form" = {
 *       "default" = "Drupal\elasticsearch\Form\ClusterForm",
 *       "edit" = "Drupal\elasticsearch\Form\ClusterForm",
 *       "delete" = "Drupal\elasticsearch\Form\ClusterDeleteConfirmForm",
 *       "disable" = "Drupal\elasticsearch\Form\ClusterDisableConfirmForm",
 *       "clear" = "Drupal\elasticsearch\Form\ClusterClearConfirmForm"
 *     },
 *   },
 *   admin_permission = "administer search_api",
 *   config_prefix = "cluster",
 *   entity_keys = {
 *     "id" = "machine_name",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "status" = "status"
 *   },
 *   links = {
 *     "canonical" = "elasticsearch.cluster_info",
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
   *
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
    return \Drupal::state()->get('elasticsearch_get_default', '');
  }

  /**
   * Set the default (cluster) used for elasticsearch.
   *
   * @return string
   */
  public static function setDefaultCluster($cluster_id) {
    return \Drupal::state()->set('elasticsearch_get_default', $cluster_id); 
  }

  /**
   * Return cluster info.
   * @return return array
   */
  public function getClusterInfo() {
    $result = FALSE;
    try {
      $client = $this->getClusterByUrls(array($this->url));
      if (!empty($client)) {
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
    }
    catch (Exception $e) {
      throw $e;
    }

    return $result;
  }
  /**
   * Return the cluster object based on Cluster ID.
   *
   * @param string $cluster_id
   * @param boolean
   * @return \Elasticsearch\Client $client
   */
  protected function getClusterById($cluster_id = NULL) {
    $default_cluster = getDefaultCluster();
    if (!isset($cluster_id) && !empty($default_cluster)) {
      $cluster_id = getDefaultCluster();
    }

    if (!empty($cluster_id)) {
      $client = FALSE;
      $cluster = loadCluster($cluster_id);
      if ($cluster) {
        $client = getClusterByUrls($cluster->url);
      }
    }

    return $client;
  }

  /**
   * We need to handle the case where url is and array of urls
   * @param string $url
   * @return
   */
  protected function getClusterByUrls($urls) {
    // TODO: Handle cluster connection. This should be accomplished if the setting is enabled.
    // If enabled, discover all the nodes in the cluster initialize the Pool connection.
    //$this->isValid($urls);

    $options = array(
      'hosts' => $urls,
    );

    \Drupal::moduleHandler()->alter('elasticsearch_load_library_options', $options);
    return new Client($options);
  }

  /**
   * Check if the status is OK.
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

  /**
  * Load a cluster object from the database.
  *
  * @see ctools_export_load_object().
  *
  * @param string $cluster_id
  * @return object $cluster
  */
  public function loadCluster($cluster_id) {
    // TODO: Remove ctools
    //ctools_include('export');
    //$result = ctools_export_load_object('elasticsearch_cluster', 'names', array($cluster_id));
    if (isset($result[$cluster_id])) {
      if (isset($result[$cluster_id]->options) && !is_array($result[$cluster_id]->options)) {
        $result[$cluster_id]->options = unserialize($result[$cluster_id]->options);
      }
      return $result[$cluster_id];
    }
  }
}
