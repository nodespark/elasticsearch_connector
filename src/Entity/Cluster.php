<?php

/**
 * @file
 * Definition of Drupal\elasticsearch_connector\Entity\Cluster.
 */

namespace Drupal\elasticsearch_connector\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\elasticsearch_connector\ClusterInterface;
use Elasticsearch\Client;
use Drupal\Component\Utility\UrlHelper;

/**
 * Defines a View configuration entity class.
 *
 * @ConfigEntityType(
 *   id = "elasticsearch_connector_cluster",
 *   label = @Translation("Elasticsearch Cluster"),
 *   controllers = {
 *     "access" = "Drupal\elasticsearch_connector\ClusterAccessController",
 *     "form" = {
 *       "add" = "Drupal\elasticsearch_connector\ClusterForm",
 *       "edit" = "Drupal\elasticsearch_connector\ClusterForm",
 *       "delete" = "Drupal\elasticsearch_connector\Form\ClusterDeleteForm"
 *     },
 *     "list_builder" = "Drupal\elasticsearch_connector\ClusterListBuilder",
 *   },
 *   links = {
 *     "add-form" = "elasticsearch_connector.add",
 *     "edit-form" = "elasticsearch_connector.edit",
 *     "delete-form" = "elasticsearch_connector.delete",
 *     "admin-form" = "elasticsearch_connector.add"
 *   },
 *   admin_permission = "administer elasticsearch connector",
 *   entity_keys = {
 *     "id" = "cluster_id",
 *     "label" = "name",
 *     "status" = "status"
 *   }
 * )
 */
class Cluster extends ConfigEntityBase implements ClusterInterface {

  // Active status
  const STATUS_ACTIVE = 1;

  // Inactive status
  const STATUS_INACTIVE = 0;

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
   * The flag indicating that the cluster is the default one.
   * @var int
   */
  public $default;

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
   *
   */
  public static function getClusterInfoByParams() {

  }
  /**
   * Get the default connector (cluster) used for elasticsearch.
   *
   * @return string
   */
  public static function getDefaultConnector() {
  //return variable_get('elasticsearch_connector_get_default_connector', '');
  // TODO: Handle default connection!
  return '';
}

  /**
   * Return cluster info.
   * @return return array
   */
  public function getClusterInfo() {
    $result = FALSE;
    try {
      $client = $this->getLibraryByUrls(array($this->url));
      if (!empty($client)) {
        $info = $client->info();
        $result['client'] = $client;
        $result['info'] = $result['state'] = $result['health'] = $result['stats'] = array();
        if (self::checkStatus($info)) {
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

  protected function checkValidUrls($urls) {

  }

  /**
   * We need to handle the case where url is and array of urls
   * @param string $url
   * @return
   */
  protected function getLibraryByUrls($urls) {
    // TODO: Handle cluster connection. This should be accomplished if the setting is enabled.
    // If enabled, discover all the nodes in the cluster initialize the Pool connection.
    $this->checkValidUrls($urls);

    $options = array(
      'hosts' => $urls,
    );

    drupal_alter('elasticsearch_connector_load_library_options', $options);
    return new Client($options);
  }

  /**
   * Check if the status is OK.
   * @param array $status
   * @return bool
   */
  public static function checkStatus($status) {
    if (is_array($status) && $status['status'] == ELASTICSEARCH_CONNECTOR_CLUSTER_STATUS_OK) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

}
