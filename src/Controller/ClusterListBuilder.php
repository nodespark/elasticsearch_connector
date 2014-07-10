<?php
/**
 * @file
 * Contains \Drupal\elasticsearch\Controller\ClusterListBuilder.
 */
namespace Drupal\elasticsearch\Controller;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
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
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, UrlGeneratorInterface $url_generator) {
    parent::__construct($entity_type, $storage);
    $this->urlGenerator = $url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity.manager')->getStorage($entity_type->id()),
      $container->get('url_generator')
    );
  }

  public function buildHeader() {
    $header['name'] = t('Cluster name');
    $header['status'] = array(
      'data' => t('Status'),
      'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
    );

    $header['status'] = array(
      'data' => t('Status'),
      'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
    );

    $header['cluster_status'] = array(
      'data' => t('Cluster Status')
    );

    $header['operations'] = $this->t('Operations');
    return $header;
  }
  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['title'] = array(
      'data' => $this->getLabel($entity),
      'class' => array('cluster-label'),
    );
    $row['status'] = $entity->status;
    // TODO: Fix the status to come from
    $row['cluster_status'] = 'green';
    $row['operations'] = t('<a href="@link0">Info</a> | <a href="@link1">Edit</a> | <a href="@link2">Indicies</a> | <a href="@link3">Delete</a>', array(
      '@link0' => \Drupal::urlGenerator()->generateFromPath('admin/config/search/elasticsearch/clusters/' . $entity->cluster_id . '/info'),
      '@link1' => \Drupal::urlGenerator()->generateFromPath('admin/config/search/elasticsearch/clusters/' . $entity->cluster_id . '/edit'),
      '@link2' => \Drupal::urlGenerator()->generateFromPath('admin/config/search/elasticsearch/clusters/' . $entity->cluster_id . '/indicies'),
      '@link3' => \Drupal::urlGenerator()->generateFromPath('admin/config/search/elasticsearch/clusters/' . $entity->cluster_id . '/delete'),

    ));
    return $row + parent::buildRow($entity);
  }

  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    return $operations;
  }

  public function load() {
    return parent::load();
  } 

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    $build['#empty'] = t('No clusters available. <a href="@link">Add new cluster</a>.', array(
      '@link' => \Drupal::urlGenerator()->generateFromPath('admin/config/search/elasticsearch/clusters/add'),
    ));
    return $build;
  }

}
?>
