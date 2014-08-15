<?php
/**
 * @file
 * Contains \Drupal\elasticsearch\Controller\ClusterListBuilder.
 */
namespace Drupal\elasticsearch\Controller;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\elasticsearch\Entity\Cluster;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of Clusters along with their indices.
 */
class ClusterListBuilder extends ConfigEntityListBuilder {
  /**
   * {@inheritdoc}
   */

  protected $indexStorage;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity.manager')->getStorage($entity_type->id()),
      $container->get('entity.manager')->getStorage('elasticsearch_cluster_index')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, EntityStorageInterface $index_storage) {
    parent::__construct($entity_type, $storage);
    $this->indexStorage = $index_storage;
  }


  /**
   * {@inheritdoc}
   */
  public function load() {
    $clusters = $this->storage->loadMultiple();
    $indices = $this->indexStorage->loadMultiple();

    $cluster_groups = array();
    foreach ($clusters as $cluster) {
      $cluster_group = array(
        "cluster." . $cluster->cluster_id => $cluster,
      );

      foreach ($indices as $index) {
        if ($index->server == $cluster->cluster_id)
          $cluster_group["index." . $index->index_id] = $index;
      }
      $cluster_groups["cluster." . $cluster->cluster_id] = $cluster_group;
    }

    return $cluster_groups;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
      return array(
        'type' => $this->t('Type'),
        'title' => $this->t('Name'),
        'status' => $this->t('Status'),
        'clusterStatus' => $this->t('Cluster Status'),
      ) + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row = parent::buildRow($entity);
    $result = array();
    $status = NULL;
    if (isset($entity->cluster_id)) {
      $cluster = Cluster::loadCluster($entity->cluster_id);
      $client_info = Cluster::getClusterInfo($cluster);
      if (!empty($client_info['info']) && Cluster::checkClusterStatus($client_info['info'])) {
        $status = $client_info['health']['status'];
      }
      else {
        $status = t('Not available');
      }
      $result = array(
        'data' => array(
          'type' => array(
            'data' => $this->t('Cluster'),
          ),
          'title' => array(
            'data' => array(
              '#type' => 'link',
              '#title' => $entity->label(),
            ) + $entity->urlInfo('info')->toRenderArray(),
          ),
          'status' => array(
            'data' => $cluster->status,
          ),
          'clusterStatus' => array(
            'data' => $status,
          ),
          'operations' => $row['operations'],
        ),
        'title' => $this->t('Machine name: @name', array('@name' => $entity->id())),
      );
    } else if (isset($entity->index_id)) {
      $result = array(
        'data' => array(
          'type' => array(
            'data' => $this->t('Index'),
          ),
          'title' => array(
            'data' => $entity->label(),
          ),
          'status' => array(
            'data' => '',
          ),
          'clusterStatus' => array(
            'data' => '-',
          ),
          'operations' => $row['operations'],
        ),
        'title' => $this->t('Machine name: @name', array('@name' => $entity->id())),
      );
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);

    if (isset($entity->cluster_id)) {
      $operations['info'] = array(
        'title' => $this->t('Info'),
        'weight' => 20,
        'route_name' => 'elasticsearch.cluster_info',
        'route_parameters' => array(
          'elasticsearch_cluster' => $entity->id(),
        ),
      );
    } elseif (isset($entity->index_id)) {
      $operations['delete'] = array(
        'title' => $this->t('Delete'),
        'weight' => 20,
        'route_name' => 'elasticsearch.clusterindex_delete',
        'route_parameters' => array(
          'elasticsearch_cluster_index' => $entity->id(),
        ),
      );
    }
    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $entity_groups = $this->load();
    $list['#type'] = 'container';
    $list['clusters'] = array(
      '#type' => 'table',
      '#header' => $this->buildHeader(),
      '#rows' => array(),
      '#empty' => $this->t('No clusters available. <a href="@link">Add new cluster</a>.', array(
      '@link' => \Drupal::urlGenerator()->generate('elasticsearch.cluster_add'),
      )),
    );
    foreach ($entity_groups as $cluster_group) {
      foreach ($cluster_group as $entity) {
        $list['clusters']['#rows'][$entity->id()] = $this->buildRow($entity);
      }
    }
    return $list;
  }
}
?>
