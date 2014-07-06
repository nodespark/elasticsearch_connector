<?php
/**
 * @file
 * Contains \Drupal\elasticsearch\Controller\ClusterListBuilder.
 */
namespace Drupal\elasticsearch\Controller;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
/**
 * Provides a listing of Example.
 */
class ClusterListBuilder extends ConfigEntityListBuilder {
  /**
   * {@inheritdoc}
   */
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
    $row['operations'] = t('<a href="@link">Edit</a>', array(
      '@link' => \Drupal::urlGenerator()->generateFromPath('admin/config/search/elasticsearch/clusters/edit'),
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