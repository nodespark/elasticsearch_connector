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
 *   },
 *   links = {
 *     "canonical" = "/admin/config/elasticsearch-connector/clusters",
 *     "add-form" = "/admin/config/elasticsearch-connector/indices/add",
 *     "delete-form" = "/admin/config/elasticsearch-connector/indices/{elasticsearch_index}/delete",
 *   }
 * )
 */
class Index extends ConfigEntityBase {

  /**
  * {@inheritdoc}
  */
  public $name;

  public $index_id;

  public $num_of_shards;

  public $num_of_replica;

  public $server;

  public function id() {
    return isset($this->index_id) ? $this->index_id : NULL;
  }

  /**
   * Load an index object
   *
   * @param $index_id
   * @return \Drupal\elasticsearch_connector\Entity\Index
   */
  public static function loadIndex($index_id) {
    return entity_load('elasticsearch_index', $index_id);
  }

  /**
   * Load all indicess
   *
   * @return \Drupal\elasticsearch_connector\Entity\Index[]
   */
  public static function loadAllIndices() {
    return entity_load_multiple('elasticsearch_index');
  }

}
