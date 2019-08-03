<?php

namespace Drupal\elasticsearch_connector\Controller;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Url;
use Drupal\elasticsearch_connector\ElasticSearch\ClientManagerInterface;
use Drupal\elasticsearch_connector\Entity\Cluster;
use Drupal\elasticsearch_connector\Entity\Index;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides a listing of Clusters along with their indices.
 */
class ClusterListBuilder extends ConfigEntityListBuilder {

  /**
   * Storage interface for the elasticsearch_index entity.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $indexStorage;

  /**
   * Storage interface for the elasticsearch_cluster entity.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $clusterStorage;

  /**
   * Elasticsearch client manager service.
   *
   * @var \Drupal\elasticsearch_connector\ElasticSearch\ClientManagerInterface
   */
  private $clientManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    EntityStorageInterface $index_storage,
    EntityStorageInterface $cluster_storage,
    ClientManagerInterface $client_manager
  ) {
    parent::__construct($entity_type, $storage);

    $this->indexStorage = $index_storage;
    $this->clusterStorage = $cluster_storage;
    $this->clientManager = $client_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(
    ContainerInterface $container,
    EntityTypeInterface $entity_type
  ) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('entity_type.manager')->getStorage('elasticsearch_index'),
      $container->get('entity_type.manager')->getStorage('elasticsearch_cluster'),
      $container->get('elasticsearch_connector.client_manager')
    );
  }

  /**
   * Group Elasticsearch indices under their respective clusters.
   *
   * @return array
   *   Associative array with the following keys:
   *   - clusters: Array of cluster groups keyed by cluster id. Each item is
   *   itself an array with the cluster and any indices as values.
   *   - lone_indexes: Array of indices without a cluster.
   */
  public function group() {
    /** @var \Drupal\elasticsearch_connector\Entity\Cluster[] $clusters */
    $clusters = $this->storage->loadMultiple();
    /** @var \Drupal\elasticsearch_connector\Entity\Index[] $indices */
    $indices = $this->indexStorage->loadMultiple();

    $cluster_groups = [];
    $lone_indices = [];
    foreach ($clusters as $cluster) {
      $cluster_group = [
        'cluster.' . $cluster->cluster_id => $cluster,
      ];

      foreach ($indices as $index) {
        if ($index->server == $cluster->cluster_id) {
          $cluster_group['index.' . $index->index_id] = $index;
        }
        elseif ($index->server == NULL) {
          $lone_indices['index.' . $index->index_id] = $index;
        }
      }

      $cluster_groups['cluster.' . $cluster->cluster_id] = $cluster_group;
    }

    return [
      'clusters' => $cluster_groups,
      'lone_indexes' => $lone_indices,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    return [
      'type' => $this->t('Type'),
      'title' => $this->t('Name'),
      'machine_name' => $this->t('Machine Name'),
      'status' => $this->t('Status'),
      'cluster_status' => $this->t('Cluster Status'),
    ] + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    if ($entity instanceof Cluster) {
      $client_connector = $this->clientManager->getClientForCluster($entity);
    }
    elseif ($entity instanceof Index) {
      $cluster = $this->clusterStorage->load($entity->server);
      $client_connector = $this->clientManager->getClientForCluster($cluster);
    }
    else {
      throw new NotFoundHttpException();
    }

    $row = parent::buildRow($entity);
    $result = [];
    $status = NULL;
    if (isset($entity->cluster_id)) {
      $cluster = $this->clusterStorage->load($entity->cluster_id);

      if ($client_connector->isClusterOk()) {
        $cluster_health = $client_connector->cluster()->health();
        $version_number = $client_connector->getServerVersion();
        $status = $cluster_health['status'];
      }
      else {
        $status = $this->t('Not available');
        $version_number = $this->t('Unknown version');
      }
      $result = [
        'data' => [
          'type' => [
            'data' => $this->t('Cluster'),
          ],
          'title' => [
            'data' => [
              '#type' => 'link',
              '#title' => $entity->label() . ' (' . $version_number . ')',
              '#url' => new Url('entity.elasticsearch_cluster.edit_form', ['elasticsearch_cluster' => $entity->id()]),
            ],
          ],
          'machine_name' => [
            'data' => $entity->id(),
          ],
          'status' => [
            'data' => $cluster->status ? 'Active' : 'Inactive',
          ],
          'clusterStatus' => [
            'data' => $status,
          ],
          'operations' => $row['operations'],
        ],
        'title' => $this->t(
          'Machine name: @name',
          ['@name' => $entity->id()]
        ),
      ];
    }
    elseif (isset($entity->index_id)) {
      $result = [
        'data' => [
          'type' => [
            'data' => $this->t('Index'),
            'class' => ['es-list-index'],
          ],
          'title' => [
            'data' => $entity->label(),
          ],
          'machine_name' => [
            'data' => $entity->id(),
          ],
          'status' => [
            'data' => '',
          ],
          'clusterStatus' => [
            'data' => '-',
          ],
          'operations' => $row['operations'],
        ],
        'title' => $this->t(
          'Machine name: @name',
          ['@name' => $entity->id()]
        ),
      ];
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $operations = [];

    if (isset($entity->cluster_id)) {
      $operations['info'] = [
        'title' => $this->t('Info'),
        'weight' => 19,
        'url' => new Url('entity.elasticsearch_cluster.canonical', ['elasticsearch_cluster' => $entity->id()]),
      ];
      $operations['edit'] = [
        'title' => $this->t('Edit'),
        'weight' => 20,
        'url' => new Url('entity.elasticsearch_cluster.edit_form', ['elasticsearch_cluster' => $entity->id()]),
      ];
      $operations['delete'] = [
        'title' => $this->t('Delete'),
        'weight' => 21,
        'url' => new Url('entity.elasticsearch_cluster.delete_form', ['elasticsearch_cluster' => $entity->id()]),
      ];
    }
    elseif (isset($entity->index_id)) {
      $operations['delete'] = [
        'title' => $this->t('Delete'),
        'weight' => 20,
        'url' => new Url('entity.elasticsearch_index.delete_form', ['elasticsearch_index' => $entity->id()]),
      ];
    }

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $entity_groups = $this->group();

    $rows = [];
    foreach ($entity_groups['clusters'] as $cluster_group) {
      /** @var \Drupal\elasticsearch_connector\Entity\Cluster|\Drupal\elasticsearch_connector\Entity\Index $entity */
      foreach ($cluster_group as $entity) {
        $rows[$entity->id()] = $this->buildRow($entity);
      }
    }

    $list['#type'] = 'container';
    $list['#attached']['library'][] = 'elasticsearch_connector/drupal.elasticsearch_connector.ec_index';
    $list['clusters'] = [
      '#type' => 'table',
      '#header' => $this->buildHeader(),
      '#rows' => $rows,
      '#empty' => $this->t(
        'No clusters available. <a href="@link">Add new cluster</a>.',
        [
          '@link' => \Drupal::urlGenerator()->generate(
            'entity.elasticsearch_cluster.add_form'
          ),
        ]
      ),
    ];

    return $list;
  }

}
