<?php

namespace Drupal\elasticsearch\Index;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Elasticsearch\Cluster;
use Drupal\Component\Utility\UrlHelper;

class Index extends ConfigEntityBase{

  /**
  * The index name.
  *
  * @var string
  */
  public $index_name;

  /**
   * Number of shards.
   *
   * @var string
   */
  public $num_of_shards;

  /**
   * Number of replica
   *
   * @var string
   */
  public $num_of_replica;

  /**
   * Actions of the cluster indices.
   * @var array
   */
  public $actions;

  /**
   * Elasticsearch display all indices in cluster.
   *
   * @param object
   * @return array
   */
  public function getClusterIndices($cluster) {
    $headers = array(
      array('data' => t('Index name')),
      array('data' => t('Docs')),
      array('data' => t('Size')),
      array('data' => t('Operations')),
    );

    $rows = array();
    $cluster_info = getClustersInfo($cluster);
    $client = $cluster_info['client'];

    if ($client && !empty($cluster_info['info']) && checkClusterStatus($cluster_info['info'])) {
      $indices = $client->indices()->stats();
      foreach ($indices['indices'] as $index_name => $index_info) {
        $row = array();

        // TODO: Remove theme() as per D8 API
        $operations = theme('links__ctools_dropbutton', array(
          'links' => array(
            array('title' => t('Aliases'), 'href' => 'admin/config/elasticsearch/clusters/' . $cluster->cluster_id . '/indices/' . $index_name . '/aliases'),
            array('title' => t('Delete'), 'href' => 'admin/config/elasticsearch/clusters/' . $cluster->cluster_id . '/indices/' . $index_name . '/delete'),
          ),
          'attributes' => array(
            'class' => array('links', 'inline'),
          ),
        ));

        $row[] = $index_name;
        $row[] = $index_info['total']['docs']['count'];
        $row[] = format_size($index_info['total']['store']['size_in_bytes']);
        $row[] = $operations;

        $rows[] = $row;
      }
    }
    else {
      drupal_set_message(t('The cluster cannot be connected for some reason.'), 'error');
    }

    $output['elasticsearch']['table'] = array(
      '#theme' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#attributes' => array('class' => array('admin-elasticsearch-indices')),
    );

    return $output;
  }

  /**
   * List all aliases for an index.
   *
   * @param object $cluster
   * @param string $index_name
   * @return array
   */
  function getClusterIndicesAliases($cluster, $index_name) {
    $headers = array(
      array('data' => t('Alias name')),
    );

    $rows = array();

    $cluster_info = getClustersInfot($cluster);
    $client = $cluster_info['client'];

    if ($client && !empty($cluster_info['info']) && checkClusterStatus($cluster_info['info'])) {
      try {
        // getAliases() from elasticsearch php-lib
        $aliases = $client->indices()->getAliases(array('index' => $index_name));
        foreach ($aliases[$index_name]['aliases'] as $alias_name => $alias_info) {
          $row = array();

          // TODO: Handle alias actions.
          $row[] = $alias_name;

          $rows[] = $row;
        }
      }
      catch (Exception $e) {
        drupal_set_message($e->getMessage(), 'error');
      }
    }
    else {
      drupal_set_message(t('The cluster cannot be connected for some reason.'), 'error');
    }

    $output['elasticsearch']['table'] = array(
      '#theme' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#attributes' => array('class' => array('admin-elasticsearch-alias')),
    );

    return $output;
  }


}