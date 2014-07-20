<?php

namespace Drupal\elasticsearch\Controller;

use Drupal\Component\Utility\String;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\elasticsearch\Entity\Cluster;
use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Example page controller.
 */
class ElasticSearchController extends ControllerBase {

  /**
   * Displays information about a Search API index.
   *
   * @param \Drupal\search_api\Index\IndexInterface $search_api_index
   *   An instance of IndexInterface.
   *
   * @return array
   *   An array suitable for drupal_render().
   */
  public function page(ConfigEntityBase $elasticsearch_cluster) {
    // Build the Search API index information.
    //print_r($elasticsearch_cluster);
    $render = array(
      'view' => array(
        '#theme' => 'elasticsearch_cluster',
        '#cluster' => $elasticsearch_cluster,
      ),
    );
    // Check if the index is enabled and can be written to.
    if ($elasticsearch_cluster->cluster_id) {
      //debug
      //print_r($elasticsearch_cluster);
      $render['form'] = $this->formBuilder()->getForm('Drupal\elasticsearch\Form\ClusterForm', $elasticsearch_cluster);
      echo 'hi';
    }
    return $render;
  }

  public function pageTitle(ConfigEntityBase $elasticsearch_cluster) {
    if ($elasticsearch_cluster->cluster_id) {
      return String::checkPlain($elasticsearch_cluster->label());
    }
    else {
      return String::checkPlain('Elasticsearch Cluster');      
    }
  }

  public function getInfo(ConfigEntityBase $elasticsearch_cluster) {
    //print_r($elasticsearch_cluster);
    $cluster_status = Cluster::getClusterInfo($elasticsearch_cluster);
    //print_r($cluster_status);
    $cluster_client = $cluster_status['client'];

    $node_rows = $cluster_statistics_rows = $cluster_health_rows = array();

    if (isset($cluster_client) && !empty($cluster_status['info']) && Cluster::checkClusterStatus($cluster_status['info'])) {
      $node_stats = $cluster_status['stats'];
      $total_docs = $total_size = 0;
      if (isset($node_stats)) {
        foreach ($node_stats['nodes'] as $node_key => $node_values) {
          $row = array();
          $row[] = array('data' => $node_values['name']);
          $row[] = array('data' => $node_values['indices']['docs']['count']);
          $row[] = array('data' => format_size($node_values['indices']['store']['size_in_bytes']));
          $total_docs += $node_values['indices']['docs']['count'];
          $total_size += $node_values['indices']['store']['size_in_bytes'];
          $node_rows[] = $row;
        }
      }

      $cluster_statistics_rows = array(
        array(
          array('data' => $cluster_status['health']['number_of_nodes'] . '<br/>' . t('Nodes')),
          array('data' => $cluster_status['health']['active_shards'] + $cluster_status['health']['unassigned_shards']
                . '<br/>' . t('Total Shards')),
          array('data' => $cluster_status['health']['active_shards'] . '<br/>' . t('Successful Shards')),
          array('data' => count($cluster_status['state']['metadata']['indices']) . '<br/>' . t('Indices')),
          array('data' => $total_docs . '<br/>' . t('Total Documents')),
          array('data' => format_size($total_size) . '<br/>' . t('Total Size')),
        )
      );

      $cluster_health_rows = array();
      $cluster_health_mapping = array(
        'cluster_name'  => t('Cluster name'),
        'status'        => t('Status'),
        'timed_out'     => t('Time out'),
        'number_of_nodes' => t('Number of nodes'),
        'number_of_data_nodes'  => t('Number of data nodes'),
        'active_primary_shards' => t('Active primary shards'),
        'active_shards'         => t('Active shards'),
        'relocating_shards'     => t('Relocating shards'),
        'initializing_shards'   => t('Initializing shards'),
        'unassigned_shards'     => t('Unassigned shards')
      );

      foreach ($cluster_status['health'] as $health_key => $health_value) {
        $row = array();
        $row[] = array('data' => $cluster_health_mapping[$health_key]);
        $row[] = array('data' => ($health_value === FALSE ? 'False' : $health_value));
        $cluster_health_rows[] = $row;
      }
    }

    $output['cluster_statistics_wrapper'] = array(
      '#type' => 'fieldset',
      '#title'  => t('Cluster statistics'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE
    );

    $output['cluster_statistics_wrapper']['nodes'] = array(
      '#theme' => 'table',
      '#header' => array(
        array('data' => t('Node name')),
        array('data' => t('Documents')),
        array('data' => t('Size')),
      ),
      '#rows' => $node_rows,
    );

    $output['cluster_statistics_wrapper']['cluster_statistics'] = array(
      '#theme' => 'table',
      '#header' => array(
        array('data' => t('Total'), 'colspan' => 6),
      ),
      '#rows' => $cluster_statistics_rows,
      '#attributes' => array('class' => array('admin-elasticsearch-statistics')),
    );

    $output['cluster_health'] = array(
      '#theme' => 'table',
      '#header' => array(
        array('data' => t('Cluster Health'), 'colspan' => 2),
      ),
      '#rows' => $cluster_health_rows,
      '#attributes' => array('class' => array('admin-elasticsearch-health')),
    );

    return $output;
  }

  /**
   * Display all indices in cluster.
   *
   * @param object
   * @return array
   */
  public function elasticsearchClusterIndices(ConfigEntityBase $elasticsearch_cluster) {
    $headers = array(
      array('data' => t('Index name')),
      array('data' => t('Docs')),
      array('data' => t('Size')),
      array('data' => t('Operations')),
    );

    $rows = array();
    $cluster_info = Cluster::getClusterInfo($elasticsearch_cluster);
    $client = $cluster_info['client'];

    if ($client && !empty($cluster_info['info']) && Cluster::checkClusterStatus($cluster_info['info'])) {
      $indices = $client->indices()->stats();
      foreach ($indices['indices'] as $index_name => $index_info) {
        $row = array();

        $operations = theme('links', array(
          'links' => array(
            array('title' => t('Aliases'), 'href' => 'admin/config/search/elasticsearch/clusters/' . $elasticsearch_cluster->cluster_id . '/indices/' . $index_name . '/aliases'),
            array('title' => t('Delete'), 'href' => 'admin/config/search/elasticsearch/clusters/' . $elasticsearch_cluster->cluster_id . '/indices/' . $index_name . '/delete'),
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

}
