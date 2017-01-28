<?php

namespace Drupal\elasticsearch_connector\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\EntityRouteProviderInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides routes for elasticsearch clusters.
 */
class IndexRouteProvider implements EntityRouteProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type) {
    $route_collection = new RouteCollection();

    // TODO: Permissions should be checked and implemented.
    $route = (new Route('/admin/config/search/elasticsearch-connector/index/{elasticsearch_index}/delete'))
      ->addDefaults(
        [
          '_entity_form' => 'elasticsearch_index.delete',
          '_title' => 'Delete index',
        ]
      )
      ->setRequirement('_entity_access', 'elasticsearch_index.delete');
    $route_collection->add('entity.elasticsearch_index.delete_form', $route);

    $route = (new Route('/admin/config/search/elasticsearch-connector/index/add'))
      ->addDefaults(
        [
          '_entity_form' => 'elasticsearch_index.default',
          '_title' => 'Add index',
        ]
      )
      ->setRequirement('_entity_create_access', 'elasticsearch_index');
    $route_collection->add('entity.elasticsearch_index.add_form', $route);

    return $route_collection;
  }

}
