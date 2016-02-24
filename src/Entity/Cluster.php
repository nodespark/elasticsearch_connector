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
    return \Drupal::state()->get(
      'elasticsearch_connector_get_default_connector',
      ''
    );
  }

  /**
   * Set the default (cluster) used for elasticsearch.
   *
   * @return string
   */
  public static function setDefaultCluster($cluster_id) {
    return \Drupal::state()->set(
      'elasticsearch_connector_get_default_connector',
      $cluster_id
    );
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
   * Get the full base URL of the cluster, including any authentication
   *
   * @param bool $safe If True (default), the the password will be starred out
   *
   * @return string
   */
  public function getBaseUrl($safe = TRUE) {
    $options = $this->options;
    if ($options['use_authentication']) {
      if ($options['username'] && $options['password']) {
        $schema = file_uri_scheme($this->url);
        $host = file_uri_target($this->url);
        $user = $options['username'];

        if ($safe) {
          return $schema . '://' . $user . ':****@' . $host;
        }
        else {
          return $schema . '://' . $user . ':' . $options['password'] . '@' . $host;
        }
      }
    }

    return $this->url;
  }
}
