<?php

/**
 * @file
 * Contains \Drupal\elasticsearch_connector\Entity\Index.
 */

namespace Drupal\elasticsearch_connector\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the search server configuration entity.
 *
 * @ConfigEntityType(
 *   id = "elasticsearch_index",
 *   label = @Translation("Elasticsearch Cluster Indices"),
 *   handlers = {
 *     "storage" = "Drupal\Core\Config\Entity\ConfigEntityStorage",
 *     "list_builder" = "Drupal\elasticsearch_connector\Controller\IndexListBuilder",
 *     "form" = {
 *       "default" = "Drupal\elasticsearch_connector\Form\IndexForm",
 *       "delete" = "Drupal\elasticsearch_connector\Form\IndexDeleteForm",
 *     },
 *   },
 *   admin_permission = "administer elasticsearch",
 *   config_prefix = "index",
 *   entity_keys = {
 *     "id" = "index_id",
 *     "label" = "name",
 *     "num_of_shards" = "num_of_shards",
 *     "num_of_replica" = "num_of_replica",
 *     "server" = "server"
 *   },
 *   links = {
 *     "canonical" = "/admin/config/search/elasticsearch-connector/clusters",
 *     "add-form" = "/admin/config/search/elasticsearch-connector/indices/add",
 *     "delete-form" = "/admin/config/search/elasticsearch-connector/indices/{elasticsearch_index}/delete",
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
   * @var array
   */
  public $server;

  public function id() {
    return isset($this->index_id) ? $this->index_id : NULL;
  }

  /**
   * Loads index object.
   *
   * @param $index_id
   *
   * @return \Drupal\elasticsearch_connector\Entity\Index
   */
  public static function loadIndex($index_id) {
    return Index::load($index_id);
  }

  /**
   * Load all indices.
   *
   * @return \Drupal\elasticsearch_connector\Entity\Index[]
   */
  public static function loadAllIndices() {
    return Index::loadMultiple();
  }

}
