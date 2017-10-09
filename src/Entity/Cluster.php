<?php

namespace Drupal\elasticsearch_connector\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the Elasticsearch Connector Cluster configuration entity.
 *
 * @ConfigEntityType(
 *   id = "elasticsearch_cluster",
 *   label = @Translation("Elasticsearch Cluster"),
 *   handlers = {
 *     "list_builder" =
 *   "Drupal\elasticsearch_connector\Controller\ClusterListBuilder",
 *     "form" = {
 *       "default" = "Drupal\elasticsearch_connector\Form\ClusterForm",
 *       "delete" = "Drupal\elasticsearch_connector\Form\ClusterDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\elasticsearch_connector\Entity\ClusterRouteProvider",
 *     },
 *   },
 *   admin_permission = "administer elasticsearch cluster",
 *   config_prefix = "cluster",
 *   entity_keys = {
 *     "id" = "cluster_id",
 *     "label" = "name",
 *     "status" = "status",
 *     "url" = "url",
 *     "options" = "options",
 *   },
 *   config_export = {
 *     "cluster_id",
 *     "name",
 *     "status",
 *     "url",
 *     "options",
 *   }
 * )
 */
class Cluster extends ConfigEntityBase {

  // Active status.
  const ELASTICSEARCH_CONNECTOR_STATUS_ACTIVE = 1;

  // Inactive status.
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
   * Get the full base URL of the cluster, including any authentication.
   *
   * @return string
   */
  public function getSafeUrl() {
    $options = $this->options;
    $url_parsed = parse_url($this->url);
    if ($options['use_authentication']) {
      return $url_parsed['scheme'] . '://'
      . $options['username'] . ':****@'
      . $url_parsed['host']
      . (isset($url_parsed['port']) ? ':' . $url_parsed['port'] : '');
    }
    else {
      return $url_parsed['scheme'] . '://'
      . (isset($url_parsed['user']) ? $url_parsed['user'] . ':****@' : '')
      . $url_parsed['host']
      . (isset($url_parsed['port']) ? ':' . $url_parsed['port'] : '');
    }
  }

  /**
   * Get the raw url.
   *
   * @return string
   */
  public function getRawUrl() {
    return $this->url;
  }

}
