<?php
/**
 * @file
 * Contains \Drupal\elasticsearch_connector\Controller\ClusterListBuilder.
 */
namespace Drupal\elasticsearch_connector\Controller;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\elasticsearch_connector\Entity\Cluster;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

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
      $container->get('entity.manager')->getStorage('elasticsearch_index')
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
    $lone_indices = array();
    foreach ($clusters as $cluster) {
      $cluster_group = array(
        'cluster.' . $cluster->cluster_id => $cluster,
      );
      foreach ($indices as $index) {
        if ($index->server == $cluster->cluster_id) {
          $cluster_group['index.' . $index->index_id] = $index;
        }
        else if ($index->server == NULL) {
          $lone_indices['index.' . $index->index_id] = $index;
        }
      }
      $cluster_groups['cluster.' . $cluster->cluster_id] = $cluster_group;
    }
    $cluster_groups['cluster.lone'] = $lone_indices;

    return $cluster_groups;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
      return array(
        'type' => $this->t('Type'),
        'title' => $this->t('Name'),
        'machine_name' => $this->t('Machine Name'),
        'status' => $this->t('Status'),
        'cluster_status' => $this->t('Cluster Status'),
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
      $cluster = Cluster::load($entity->cluster_id);
      $client_info = $cluster->getClusterInfo();

      if (!empty($client_info['health']) && $cluster->checkClusterStatus()) {
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
            ) + $entity->urlInfo('canonical')->toRenderArray(),
          ),
          'machine_name' => array(
            'data' => $entity->id(),
          ),
          'status' => array(
            'data' => $cluster->status ? 'Active' : 'Inactive',
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
          'machine_name' => array(
            'data' => $entity->id(),
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
        'url' => new Url('entity.elasticsearch_cluster.canonical', array('elasticsearch_cluster' => $entity->id())),
      );
    } elseif (isset($entity->index_id)) {
      $operations['delete'] = array(
        'title' => $this->t('Delete'),
        'weight' => 20,
        'url' => new Url('entity.elasticsearch_index.delete_form', array('elasticsearch_index' => $entity->id())),
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
      '@link' => \Drupal::urlGenerator()->generate('entity.elasticsearch_cluster.add_form'),
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
