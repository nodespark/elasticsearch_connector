<?php

namespace Drupal\elasticsearch_connector\ElasticSearch;

use Drupal\Core\Extension\ModuleHandlerInterface;
// TODO: Cluster should be an interface!
use Drupal\elasticsearch_connector\Entity\Cluster;
use nodespark\DESConnector\ClientFactoryInterface;

/**
 *
 */
interface ClientManagerInterface {

  /**
   *
   */
  public function __construct(ModuleHandlerInterface $module_handler, ClientFactoryInterface $clientManagerFactory);

  /**
   * @param \Drupal\elasticsearch_connector\Entity\Cluster $cluster
   *
   * @return \nodespark\DESConnector\ClientInterface
   */
  public function getClientForCluster(Cluster $cluster);

}
