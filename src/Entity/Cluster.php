<?php

/**
 * @file
 * Definition of Drupal\elasticsearch_connector\Entity\Cluster.
 */

namespace Drupal\elasticsearch_connector\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\elasticsearch_connector\ClusterInterface;

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
   * The cluster description.
   *
   * @var string
   */
  public $description;

  /**
   * The locked status of this menu.
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
}
