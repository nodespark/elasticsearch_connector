<?php

namespace Drupal\Tests\elasticsearch_connector\Unit\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\elasticsearch_connector\Entity\IndexRouteProvider;

/**
 * @coversDefaultClass \Drupal\elasticsearch_connector\Entity\IndexRouteProvider
 *
 * @group elasticsearch_connector
 */
class IndexRouteProviderTest extends UnitTestCase {

  /**
   * @covers ::getRoutes
   */
  public function testGetRoutes() {
    $cluster_route_provider = new IndexRouteProvider();
    $entity_type = $this->prophesize(EntityTypeInterface::class);

    /** @var \Symfony\Component\Routing\RouteCollection $route_collection */
    $route_collection = $cluster_route_provider->getRoutes($entity_type->reveal());
    $this->assertEquals(2, $route_collection->count());
  }

}
