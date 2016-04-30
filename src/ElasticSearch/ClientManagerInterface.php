<?php

namespace Drupal\elasticsearch_connector\ElasticSearch;

use Drupal\Core\Extension\ModuleHandlerInterface;
// TODO: Cluster should be an interface!
use Drupal\elasticsearch_connector\Entity\Cluster;

interface ClientManagerInterface {
  public function __construct(ModuleHandlerInterface $module_handler, $clientManagerClass);
  public function getClientForCluster(Cluster $cluster);
}