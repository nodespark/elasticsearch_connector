<?php

namespace Drupal\elasticsearch\Controller;

use Drupal\Component\Utility\String;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\elasticsearch\Entity\Cluster;

/**
 * Provides route responses for elasticsearch clusters.
 */
class ElasticSearchController extends ControllerBase {

  /**
   * Displays information about an Elasticsearch Cluster.
   *
   * @param \Drupal\elasticsearch\Entity\Cluster $cluster
   *   An instance of Cluster.
   *
   * @return array
   *   An array suitable for drupal_render().
   */
  public function page(Cluster $elasticsearch_cluster) {
    // Build the Search API index information.
    $render = array(
      'view' => array(
        '#theme' => 'elasticsearch_cluster',
        '#cluster' => $elasticsearch_cluster,
      ),
    );
    // Check if the cluster is enabled and can be written to
    if ($elasticsearch_cluster->cluster_id) {
      $render['form'] = $this->formBuilder()->getForm('Drupal\elasticsearch\Form\ClusterForm', $elasticsearch_cluster);
    }
    return $render;
  }

  /**
   * Page title callback for a cluster's "View" tab.
   *
   * @param \Drupal\elasticsearch\Entity\Cluster $elasticsearch_cluster
   *   The cluster that is displayed.
   *
   * @return string
   *   The page title.
   */
  public function pageTitle(Cluster $elasticsearch_cluster) {
    return String::checkPlain($elasticsearch_cluster->label());
  }

  /**
   * Complete information about the Elasticsearch Client
   *
   * @param Cluster $elasticsearch_cluster
   * @return mixed
   * @throws \Drupal\elasticsearch\Entity\Exception
   * @throws \Exception
   */
  public function getInfo(Cluster $elasticsearch_cluster) {
    $cluster_status = Cluster::getClusterInfo($elasticsearch_cluster);
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
}
