<?php

namespace Drupal\elasticsearch_connector;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;

/**
 * ClusterManager service.
 */
class ClusterManager {

  /**
   * The state storage service.
   *
   * @var \Drupal\Node\NodeStorageInterface
   */
  protected $state;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * ClusterManager constructor.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state storage service.
   */
  public function __construct(StateInterface $state, EntityTypeManagerInterface $entity_type_manager) {
    $this->state = $state;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Get the default (cluster) used by Elasticsearch.
   *
   * @return string
   *   The cluster identifier.
   */
  public function getDefaultCluster() {
    return $this->state->get('elasticsearch_connector_get_default_connector',
      '');
  }

  /**
   * Set the default (cluster) used by Elasticsearch.
   *
   * @param string $cluster_id
   *   The new cluster identifier.
   */
  public function setDefaultCluster($cluster_id) {
    $this->state->set(
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
  public function loadAllClusters($include_inactive = TRUE) {
    $clusters = $this->entityTypeManager->getStorage('elasticsearch_cluster')->loadMultiple();
    foreach ($clusters as $cluster) {
      if (!$include_inactive && !$cluster->status) {
        unset($clusters[$cluster->cluster_id]);
      }
    }

    return $clusters;
  }


}
