<?php

namespace Drupal\elasticsearch_connector\ElasticSearch;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\elasticsearch_connector\Entity\Cluster;
use nodespark\DESConnector\ClientFactoryInterface;

/**
 * Class ClientManager.
 */
class ClientManager implements ClientManagerInterface {

  /** @var \nodespark\DESConnector\ClientInterface[] */
  protected $clients = [];

  /**
   * @var ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The class that implements
   * \nodespark\DESConnector\ClientFactoryInterface.
   *
   * @var string
   */
  protected $clientManagerFactory;

  /**
   * ConnectorManager constructor.
   *
   * @param ModuleHandlerInterface $module_handler
   */
  public function __construct(ModuleHandlerInterface $module_handler, ClientFactoryInterface $clientManagerFactory) {
    $this->moduleHandler = $module_handler;
    $this->clientManagerFactory = $clientManagerFactory;
  }

  /**
   * Get the Elasticsearch client required by the functionality.
   *
   * @param Cluster $cluster
   *
   * @return \nodespark\DESConnector\ClientInterface
   *
   * @throws \Exception
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
          $cluster->getRawUrl(),
        ),
        'options' => array(),
        'curl' => array(
          CURLOPT_CONNECTTIMEOUT => (!empty($cluster->options['timeout']) ? $cluster->options['timeout'] : Cluster::ELASTICSEARCH_CONNECTOR_DEFAULT_TIMEOUT)
        ),
      );

      if ($cluster->options['use_authentication']) {
        $options['auth'] = [
          $cluster->url => [
            'username' => $cluster->options['username'],
            'password' => $cluster->options['password'],
            'method' => $cluster->options['authentication_type'],
          ],
        ];
      }

      $this->moduleHandler->alter(
        'elasticsearch_connector_load_library_options',
        $options,
        $cluster
      );

      $this->clients[$hash] = $this->clientManagerFactory->create($options);
    }

    return $this->clients[$hash];
  }

}
