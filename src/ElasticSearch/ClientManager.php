<?php

namespace Drupal\elasticsearch_connector\ElasticSearch;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\elasticsearch_connector\Entity\Cluster;
use Elasticsearch\Client;
use GuzzleHttp\Ring\Client\CurlHandler;

/**
 * Class ClientManager
 *
 * @author andy.thorne@timeinc.com
 */
class ClientManager {

  /** @var Client[] */
  protected $clients = [];

  /**
   * @var ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * ConnectorManager constructor.
   *
   * @param ModuleHandlerInterface $module_handler
   */
  public function __construct(ModuleHandlerInterface $module_handler) {
    $this->moduleHandler = $module_handler;
  }

  /**
   * @param Cluster $cluster
   *
   * @return Client
   */
  public function getClientForCluster(Cluster $cluster) {
    $hosts = [
      [
        'url' => $cluster->url,
        'options' => $cluster->options,
      ],
    ];

    $hash = json_encode($hosts);
    if (!isset($this->clients[$hash])) {
      $options = array(
        'hosts' => array(
          $cluster->getBaseUrl(FALSE),
        ),
        'options' => array(),
        'curl' => array(),
        'handler' => new CurlHandler(),
      );
      $this->moduleHandler->alter(
        'elasticsearch_connector_load_library_options',
        $options
      );

      $this->clients[$hash] = ClientFactory::create($options);
    }

    return $this->clients[$hash];
  }
}
