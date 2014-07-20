<?php

namespace Drupal\elasticsearch\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Elasticsearch\Client;
use Drupal\Component\Utility\UrlHelper;
use Drupal\elasticsearch\Entity\Cluster;

/**
 * Defines the search server configuration entity.
 *
 * @ConfigEntityType(
 *   id = "elasticsearch_cluster_index",
 *   label = @Translation("Elasticsearch Cluster Indices"),
 *   controllers = {
 *     "storage" = "Drupal\Core\Config\Entity\ConfigEntityStorage",
 *     "list_builder" = "Drupal\elasticsearch\Controller\IndexListBuilder",
 *     "form" = {
 *       "default" = "Drupal\elasticsearch\Form\IndexForm",
 *       "edit" = "Drupal\elasticsearch\Form\IndexForm",
 *       "delete" = "Drupal\elasticsearch\Form\IndexDeleteForm",
 *     },
 *   },
 *   admin_permission = "administer elasticsearch",
 *   config_prefix = "index",
 *   entity_keys = {
 *     "label" = "index_name",
 *     "shards" = "num_of_shards",
 *     "replica" = "num_of_replica"
 *   },
 *   links = {
 *     "canonical" = "elasticsearch.clusterindex_view",
 *     "add-form" = "elasticsearch.clusterindex_add",
 *     "delete-form" = "elasticsearch.clusterindex_delete",
 *   }
 * )
 */
class Index extends ConfigEntityBase {

  /**
   * {@inheritdoc}
   */
  public $index_name;

  public $num_of_shards;

  public $num_of_replica;

}
