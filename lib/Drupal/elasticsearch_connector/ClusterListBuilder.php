<?php

/**
 * @file
 * Contains \Drupal\elasticsearch_connector\ClusterListBuilder.
 */

namespace Drupal\elasticsearch_connector;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Component\Utility\Xss;
use Drupal\Component\Utility\String;

/**
 * Defines a class to build a listing of elasticsearch cluster entities.
 *
 * @see \Drupal\elasticsearch_connector\Entity\Cluster
 */
class ClusterListBuilder extends ConfigEntityListBuilder {

  /**
   * The url generator service.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * Constructs a NodeTypeForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   The url generator service.
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

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['title'] = array(
      'data' => $this->getLabel($entity),
      'class' => array('menu-label'),
    );
    $row['description'] = Xss::filterAdmin($entity->description);
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    $build['#empty'] = t('No clusters available. <a href="@link">Add new cluster</a>.', array(
      '@link' => $this->urlGenerator->generateFromPath('admin/config/elasticsearch-connector/clusters/add'),
    ));
    return $build;
  }

}
