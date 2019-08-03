<?php

namespace Drupal\elasticsearch_connector\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

// TODO: Check the list builder.
/**
 * Defines the Elasticsearch Connector Index configuration entity.
 *
 * @ConfigEntityType(
 *   id = "elasticsearch_index",
 *   label = @Translation("Elasticsearch Index"),
 *   handlers = {
 *     "form" = {
 *       "default" = "Drupal\elasticsearch_connector\Form\IndexForm",
 *       "delete" = "Drupal\elasticsearch_connector\Form\IndexDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\elasticsearch_connector\Entity\IndexRouteProvider",
 *     },
 *   },
 *   admin_permission = "administer elasticsearch index",
 *   config_prefix = "index",
 *   entity_keys = {
 *     "id" = "index_id",
 *     "label" = "name",
 *     "num_of_shards" = "num_of_shards",
 *     "num_of_replica" = "num_of_replica",
 *     "server" = "server",
 *   },
 *   config_export = {
 *     "index_id",
 *     "name",
 *     "num_of_shards",
 *     "num_of_replica",
 *     "server",
 *   }
 * )
 */
class Index extends ConfigEntityBase {

  /**
   * The index machine name.
   *
   * @var string
   */
  public $index_id;

  /**
   * The human-readable name of the index entity.
   *
   * @var string
   */
  public $name;

  /**
   * Number of shards.
   *
   * @var string
   */
  public $num_of_shards;

  /**
   * Number of replica.
   *
   * @var string
   */
  public $num_of_replica;

  /**
   * Cluster the index is attached to.
   *
   * @var array
   */
  public $server;

  /**
   *
   */
  public function id() {
    return isset($this->index_id) ? $this->index_id : NULL;
  }

}
