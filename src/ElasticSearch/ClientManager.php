<?php

namespace Drupal\elasticsearch_connector\ElasticSearch;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\elasticsearch_connector\Entity\Cluster;
use nodespark\DESConnector\ClientFactoryInterface;

/**
 * Class ClientManager.
 */
class ClientManager implements ClientManagerInterface {

  /**
   * Array of clients keyed by JSON encoded cluster URL and options.
   *
   * @var \nodespark\DESConnector\ClientInterface[]
   */
  protected $clients = [];

  /**
   * Module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Client manager factory.
   *
   * @var \nodespark\DESConnector\ClientFactoryInterface
   */
  protected $clientManagerFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(ModuleHandlerInterface $module_handler, ClientFactoryInterface $clientManagerFactory) {
    $this->moduleHandler = $module_handler;
    $this->clientManagerFactory = $clientManagerFactory;
  }

  /**
   * {@inheritdoc}
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
      $options = [
        'hosts' => [
          $cluster->getRawUrl(),
        ],
        'options' => [],
        'curl' => [
          CURLOPT_CONNECTTIMEOUT => (!empty($cluster->options['timeout']) ? $cluster->options['timeout'] : Cluster::ELASTICSEARCH_CONNECTOR_DEFAULT_TIMEOUT),
        ],
      ];

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
