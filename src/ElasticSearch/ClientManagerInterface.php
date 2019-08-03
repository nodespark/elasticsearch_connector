<?php

namespace Drupal\elasticsearch_connector\ElasticSearch;

use Drupal\Core\Extension\ModuleHandlerInterface;
// TODO: Cluster should be an interface!
use Drupal\elasticsearch_connector\Entity\Cluster;
use nodespark\DESConnector\ClientFactoryInterface;

/**
 * Client manager interface.
 */
interface ClientManagerInterface {

  /**
   * Create a client manager.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module handler service.
   * @param \nodespark\DESConnector\ClientFactoryInterface $clientManagerFactory
   *   Client factory.
   */
  public function __construct(ModuleHandlerInterface $module_handler, ClientFactoryInterface $clientManagerFactory);

  /**
   * Get a client to interact with the given Elasticsearch cluster.
   *
   * @param \Drupal\elasticsearch_connector\Entity\Cluster $cluster
   *   Cluster to get a client for.
   *
   * @return \nodespark\DESConnector\ClientInterface
   *   Client object to interact with the given cluster.
   */
  public function getClientForCluster(Cluster $cluster);

}
