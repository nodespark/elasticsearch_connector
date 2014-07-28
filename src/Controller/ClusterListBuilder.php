<?php
/**
 * @file
 * Contains \Drupal\elasticsearch\Controller\ClusterListBuilder.
 */
namespace Drupal\elasticsearch\Controller;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Url;
use Drupal\elasticsearch\Entity\Cluster;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Component\Utility\Xss;
use Drupal\Component\Utility\String;
/**
 * Provides a listing of Example.
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

  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, EntityStorageInterface $index_storage) {
    parent::__construct($entity_type, $storage);
    //$this->urlGenerator = $url_generator;
    $this->indexStorage = $index_storage;
  }


  public function load() {
    $clusters = $this->storage->loadMultiple();
    $indices = $this->indexStorage->loadMultiple();

    //$this->sortByStatusThenAlphabetically($indexes);
    //$this->sortByStatusThenAlphabetically($clusers);

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

  public function buildHeader() {
      return array(
        'type' => $this->t('Type'),
        'title' => $this->t('Name'),
        'status' => array(
          'data' => $this->t('Status'),
          'class' => array('checkbox'),
        ),
      ) + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $cluster) {
    $row['title'] = array(
      'data' => $this->getLabel($cluster),
      'class' => array('cluster-label'),
    );
    $row['status'] = $cluster->status;

    $cluster_info = Cluster::getClusterInfo($cluster);
    if (!empty($cluster_info['info']) && Cluster::checkClusterStatus($cluster_info['info'])) {
      $row['cluster_status'] = $cluster_info['health']['status'];
    }
    else {
      $row['cluster_status'] = t('Not available');
    }

    $row['operations'] = t('<a href="@link0">Info</a> | <a href="@link1">Edit</a> | <a href="@link2">Indices</a> | <a href="@link3">Delete</a>', array(
      '@link0' => \Drupal::urlGenerator()->generateFromPath('admin/config/search/elasticsearch/clusters/' . $cluster->cluster_id . '/info'),
      '@link1' => \Drupal::urlGenerator()->generateFromPath('admin/config/search/elasticsearch/clusters/' . $cluster->cluster_id . '/edit'),
      '@link2' => \Drupal::urlGenerator()->generateFromPath('admin/config/search/elasticsearch/clusters/' . $cluster->cluster_id . '/indices'),
      '@link3' => \Drupal::urlGenerator()->generateFromPath('admin/config/search/elasticsearch/clusters/' . $cluster->cluster_id . '/delete'),

    ));
    return $row + parent::buildRow($cluster);
  }

  public function getDefaultOperations(EntityInterface $cluster) {
    $operations = parent::getDefaultOperations($cluster);
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
      '@link' => \Drupal::urlGenerator()->generateFromPath('admin/config/search/elasticsearch/clusters/add'),
      )),
    );
    foreach ($entity_groups as $cluster_group) {
      foreach ($cluster_group as $entity) {
        if (isset($entity->cluster_id)) {
          $list['clusters']['#rows'][$entity->cluster_id] = $this->buildRow($entity);
        }
      }
    }

    return $list;
  }
}
?>
