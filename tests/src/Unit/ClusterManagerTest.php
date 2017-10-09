<?php

namespace Drupal\Tests\elasticsearch_connector\Unit;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\elasticsearch_connector\ClusterManager;
use Drupal\elasticsearch_connector\Entity\Cluster;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\elasticsearch_connector\ClusterManager
 *
 * @group elasticsearch_connector
 */
class ClusterManagerTest extends UnitTestCase {

  /**
   * An instance of ClusterManager
   *
   * @var \Drupal\elasticsearch_connector\ClusterManager
   */
  protected $clusterManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $state = $this->prophesize(StateInterface::class);
    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);

    $this->clusterManager = new ClusterManager($state->reveal(), $entity_type_manager->reveal());
  }

  /**
   * @covers ::__construct
   */
  public function testConstruct() {
    $this->assertInstanceOf(ClusterManager::class, $this->clusterManager);
  }

  /**
   * @covers ::getDefaultCluster
   * @covers ::setDefaultCluster
   */
  public function testGetSetDefaultCluster() {
    $state = $this->prophesize(StateInterface::class);
    $state->get('elasticsearch_connector_get_default_connector', '')
      ->willReturn('foo');
    $state->set('elasticsearch_connector_get_default_connector', 'foo')
      ->shouldBeCalled();

    // Check the get method.
    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $this->clusterManager = new ClusterManager($state->reveal(), $entity_type_manager->reveal());
    $this->assertEquals('foo', $this->clusterManager->getDefaultCluster());

    // Check the set method (a prediction was set above).
    $this->clusterManager->setDefaultCluster('foo');
  }

  /**
   * @covers ::loadAllClusters
   */
  public function testLoadAllClusters() {
    $state = $this->prophesize(StateInterface::class);

    $cluster1 = new \stdClass();
    $cluster1->cluster_id = 'foo';
    $cluster1->status = FALSE;

    $cluster2 = new \stdClass();
    $cluster2->cluster_id = 'bar';
    $cluster2->status = TRUE;

    $entity_storage_interface = $this->prophesize(EntityStorageInterface::class);
    $entity_storage_interface->loadMultiple()
      ->willReturn([
        $cluster1->cluster_id => $cluster1,
        $cluster2->cluster_id => $cluster2,
        ]);

    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $entity_type_manager->getStorage('elasticsearch_cluster')
      ->willReturn($entity_storage_interface->reveal());

    $this->clusterManager = new ClusterManager($state->reveal(), $entity_type_manager->reveal());

    $expected_clusters = [
      $cluster2->cluster_id => $cluster2,
    ];
    $this->assertEquals($expected_clusters, $this->clusterManager->loadAllClusters(FALSE));
  }

}
