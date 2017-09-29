<?php

namespace Drupal\Tests\elasticsearch_connector\Unit\ElasticSearch;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\elasticsearch_connector\ElasticSearch\ClientManager;
use Drupal\elasticsearch_connector\Entity\Cluster;
use Drupal\Tests\UnitTestCase;
use nodespark\DESConnector\ClientFactoryInterface;
use nodespark\DESConnector\ClientInterface;
use Prophecy\Argument;

/**
 * @coversDefaultClass \Drupal\elasticsearch_connector\ElasticSearch\ClientManager
 *
 * @group elasticsearch_connector
 */
class ClientManagerTest extends UnitTestCase {

  /**
   * An instance of ClientManager
   *
   * @var \Drupal\elasticsearch_connector\ElasticSearch\ClientManager
   */
  protected $clientManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $module_handler = $this->prophesize(ModuleHandlerInterface::class);
    $client_factory = $this->prophesize(ClientFactoryInterface::class);
    $client_factory->create(Argument::type('array'))
      ->willReturn($this->prophesize(ClientInterface::class)->reveal());

    $this->clientManager = new ClientManager($module_handler->reveal(), $client_factory->reveal());
  }

  /**
   * @covers ::__construct
   */
  public function testConstruct() {
    $this->assertInstanceOf(ClientManager::class, $this->clientManager);
  }

  /**
   * @covers ::getClientForCluster
   */
  public function testGetClientForCluster() {
    $cluster = $this->prophesize(Cluster::class);
    $cluster->getRawUrl()
      ->willReturn('http://example.com');
    $cluster = $cluster->reveal();
    $cluster->options['use_authentication'] = TRUE;
    $cluster->options['username'] = 'Tom';
    $cluster->options['password'] = 'Waits';
    $cluster->options['authentication_type'] = 'basic_auth';

    $client = $this->clientManager->getClientForCluster($cluster);
    $this->assertInstanceOf(ClientInterface::class, $client);
  }

}
