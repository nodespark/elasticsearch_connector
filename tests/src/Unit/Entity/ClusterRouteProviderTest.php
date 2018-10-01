<?php

namespace Drupal\Tests\elasticsearch_connector\Unit\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\elasticsearch_connector\Entity\ClusterRouteProvider;

/**
 * @coversDefaultClass \Drupal\elasticsearch_connector\Entity\ClusterRouteProvider
 *
 * @group elasticsearch_connector
 */
class ClusterRouteProviderTest extends UnitTestCase {

  /**
   * @covers ::getRoutes
   */
  public function testGetRoutes() {
    $cluster_route_provider = new ClusterRouteProvider();
    $entity_type = $this->prophesize(EntityTypeInterface::class);

    /** @var \Symfony\Component\Routing\RouteCollection $route_collection */
    $route_collection = $cluster_route_provider->getRoutes($entity_type->reveal());
    $this->assertEquals(4, $route_collection->count());
  }

}
