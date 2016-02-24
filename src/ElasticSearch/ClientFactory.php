<?php

namespace Drupal\elasticsearch_connector\ElasticSearch;

use Elasticsearch\ClientBuilder;
use Elasticsearch\Connections\ConnectionFactory;
use Elasticsearch\Serializers\SmartSerializer;
use Psr\Log\NullLogger;

/**
 * Class ClientFactory
 *
 * @author andy.thorne@timeinc.com
 */
class ClientFactory {

  /**
   * Build an instance of the elastic search client
   *
   * @param array $options
   *
   * @return \Elasticsearch\Client
   */
  public static function create(array $options) {
    $conn_params = array();
    if (isset($options['curl'])) {
      $conn_params['client']['curl'] = $options['curl'];
    }

    $builder = ClientBuilder::create();
    $builder->setHosts($options['hosts']);

    $serializer = new SmartSerializer();
    $connectionFactory = new ConnectionFactory(
      $options['handler'],
      $conn_params,
      $serializer,
      new NullLogger(),
      new NullLogger()
    );
    $builder->setHandler($options['handler']);
    $builder->setSerializer($serializer);
    $builder->setConnectionFactory($connectionFactory);

    return $builder->build();
  }

}
