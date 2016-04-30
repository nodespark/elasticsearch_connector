<?php

namespace Drupal\elasticsearch_connector\ElasticSearch;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\elasticsearch_connector\Entity\Cluster;
use nodespark\DESConnector\ClientInterface;

/**
 * Class ClientManager
 */
class ClientManager implements ClientManagerInterface {

  /** @var \nodespark\DESConnector\ClientInterface[] */
  protected $clients = [];

  /**
   * @var ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The classname that implements \nodespark\DESConnector\ClientFactoryInterface
   * @var string
   */
  protected $clientManagerClass;

  /**
   * ConnectorManager constructor.
   *
   * @param ModuleHandlerInterface $module_handler
   */
  public function __construct(ModuleHandlerInterface $module_handler, $clientManagerClass) {
    $this->moduleHandler = $module_handler;
    $this->setClientManager($clientManagerClass);
  }

  protected function setClientManager($clientManagerClass) {
    if (!class_exists($clientManagerClass)) {
      // TODO: Handle the messages translations.
      // TODO: Create class exception to wrap the errors.
      throw new \Exception('The given parameter is not a class');
    }

    $interfaces = class_implements($clientManagerClass);
    if (is_array($interfaces) && in_array('nodespark\DESConnector\ClientFactoryInterface', $interfaces)) {
      $this->clientManagerClass = $clientManagerClass;
    }
  }

  /**
   * Get the Elasticsearch client required by the functionalities.
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
        'curl' => array(),
      );

      if ($cluster->options['use_authentication']) {
        $options['auth'] = [
          $cluster->url => [
            'username' => $cluster->options['username'],
            'password' => $cluster->options['password'],
            'method' => $cluster->options['authentication_type'],
          ]
        ];
      }

      $this->moduleHandler->alter(
        'elasticsearch_connector_load_library_options',
        $options
      );

      $this->clients[$hash] = call_user_func_array($this->clientManagerClass .'::create', array($options));

      if (! ($this->clients[$hash] instanceof ClientInterface)) {
        // TODO: Handle the exception with specific class and handle the translation.
        throw new \Exception('The instance of the class is not the supported one.');
      }
    }

    return $this->clients[$hash];
  }
}
