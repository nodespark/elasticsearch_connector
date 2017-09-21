<?php

namespace Drupal\elasticsearch_connector\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\EntityRouteProviderInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides routes for elasticsearch clusters.
 */
class ClusterRouteProvider implements EntityRouteProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type) {
    $route_collection = new RouteCollection();

    // TODO: Permissions should be checked and implemented.
    $route = (new Route('/admin/config/search/elasticsearch-connector/cluster/{elasticsearch_cluster}'))
      ->addDefaults(
        [
          '_controller' => '\Drupal\elasticsearch_connector\Controller\ElasticsearchController::getInfo',
          '_title_callback' => '\Drupal\elasticsearch_connector\Controller\ElasticsearchController::pageTitle',
          '_title' => 'Cluster Info',
        ]
      )
      ->setRequirement('_entity_access', 'elasticsearch_cluster.view')
      ->setOptions(
        [
          'parameters' => [
            'elasticsearch_cluster' => [
              'with_config_overrides' => TRUE,
            ],
          ],
        ]
      );
    $route_collection->add('entity.elasticsearch_cluster.canonical', $route);

    $route = (new Route('/admin/config/search/elasticsearch-connector/cluster/{elasticsearch_cluster}/delete'))
      ->addDefaults(
        [
          '_entity_form' => 'elasticsearch_cluster.delete',
          '_title' => 'Delete cluster',
        ]
      )
      ->setRequirement('_entity_access', 'elasticsearch_cluster.delete');
    $route_collection->add('entity.elasticsearch_cluster.delete_form', $route);

    $route = (new Route('/admin/config/search/elasticsearch-connector/cluster/{elasticsearch_cluster}/edit'))
      ->addDefaults(
        [
          '_entity_form' => 'elasticsearch_cluster.default',
          '_title_callback' => '\Drupal\elasticsearch_connector\Controller\ElasticsearchController::pageTitle',
          '_title' => 'Edit cluster',
        ]
      )
      ->setRequirement('_entity_access', 'elasticsearch_cluster.update');
    $route_collection->add('entity.elasticsearch_cluster.edit_form', $route);

    $route = (new Route('/admin/config/search/elasticsearch-connector/cluster/add'))
      ->addDefaults(
        [
          '_entity_form' => 'elasticsearch_cluster.default',
          '_title' => 'Add cluster',
        ]
      )
      ->setRequirement('_entity_create_access', 'elasticsearch_cluster');
    $route_collection->add('entity.elasticsearch_cluster.add_form', $route);

    return $route_collection;
  }

}
