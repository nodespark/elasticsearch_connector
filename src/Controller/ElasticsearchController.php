<?php

namespace Drupal\elasticsearch_connector\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\elasticsearch_connector\ElasticSearch\ClientManagerInterface;
use Drupal\elasticsearch_connector\Entity\Cluster;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides route responses for elasticsearch clusters.
 */
class ElasticsearchController extends ControllerBase {

  /**
   * Elasticsearch client manager service.
   *
   * @var \Drupal\elasticsearch_connector\ElasticSearch\ClientManagerInterface
   */
  private $clientManager;

  /**
   * ElasticsearchController constructor.
   *
   * @param \Drupal\elasticsearch_connector\ElasticSearch\ClientManagerInterface $client_manager
   *   Elasticsearch client manager service.
   */
  public function __construct(ClientManagerInterface $client_manager) {
    $this->clientManager = $client_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static (
      $container->get('elasticsearch_connector.client_manager')
    );
  }

  /**
   * Displays information about an Elasticsearch Cluster.
   *
   * @param \Drupal\elasticsearch_connector\Entity\Cluster $elasticsearch_cluster
   *   An instance of Cluster.
   *
   * @return array
   *   An array suitable for drupal_render().
   */
  public function page(Cluster $elasticsearch_cluster) {
    // Build the Search API index information.
    $render = [
      'view' => [
        '#theme' => 'elasticsearch_cluster',
        '#cluster' => $elasticsearch_cluster,
      ],
    ];
    // Check if the cluster is enabled and can be written to.
    if ($elasticsearch_cluster->cluster_id) {
      $render['form'] = $this->formBuilder()->getForm(
        'Drupal\elasticsearch_connector\Form\ClusterForm',
        $elasticsearch_cluster
      );
    }

    return $render;
  }

  /**
   * Page title callback for a cluster's "View" tab.
   *
   * @param \Drupal\elasticsearch_connector\Entity\Cluster $elasticsearch_cluster
   *   The cluster that is displayed.
   *
   * @return string
   *   The page title.
   */
  public function pageTitle(Cluster $elasticsearch_cluster) {
    // TODO: Check if we need string escaping.
    return $elasticsearch_cluster->label();
  }

  /**
   * Complete information about the Elasticsearch Client.
   *
   * @param \Drupal\elasticsearch_connector\Entity\Cluster $elasticsearch_cluster
   *   Elasticsearch cluster.
   *
   * @return array
   *   Render array.
   */
  public function getInfo(Cluster $elasticsearch_cluster) {
    // TODO: Get the statistics differently.
    $client_connector = $this->clientManager->getClientForCluster($elasticsearch_cluster);

    $node_rows = [];
    $cluster_statistics_rows = [];
    $cluster_health_rows = [];

    if ($client_connector->isClusterOk()) {
      // Nodes.
      $es_node_namespace = $client_connector->getNodesProperties();
      $node_stats = $es_node_namespace['stats'];

      $total_docs = 0;
      $total_size = 0;
      $node_rows = [];
      if (!empty($node_stats['nodes'])) {
        // TODO: Better format the results in order to build the
        // correct output.
        foreach ($node_stats['nodes'] as $node_id => $node_properties) {
          $row = [];
          $row[] = ['data' => $node_properties['name']];
          $row[] = ['data' => $node_properties['indices']['docs']['count']];
          $row[] = [
            'data' => format_size(
              $node_properties['indices']['store']['size_in_bytes']
            ),
          ];
          $total_docs += $node_properties['indices']['docs']['count'];
          $total_size += $node_properties['indices']['store']['size_in_bytes'];
          $node_rows[] = $row;
        }
      }

      $cluster_status = $client_connector->getClusterInfo();
      $cluster_statistics_rows = [
        [
          [
            'data' => $cluster_status['health']['number_of_nodes'] . ' ' . t(
                'Nodes'
            ),
          ],
          [
            'data' => $cluster_status['health']['active_shards'] + $cluster_status['health']['unassigned_shards'] . ' ' . t(
                'Total Shards'
            ),
          ],
          [
            'data' => $cluster_status['health']['active_shards'] . ' ' . t(
                'Successful Shards'
            ),
          ],
          [
            'data' => count(
                $cluster_status['state']['metadata']['indices']
            ) . ' ' . t('Indices'),
          ],
          ['data' => $total_docs . ' ' . t('Total Documents')],
          ['data' => format_size($total_size) . ' ' . t('Total Size')],
        ],
      ];

      $cluster_health_rows = [];
      $cluster_health_mapping = [
        'cluster_name' => t('Cluster name'),
        'status' => t('Status'),
        'timed_out' => t('Time out'),
        'number_of_nodes' => t('Number of nodes'),
        'number_of_data_nodes' => t('Number of data nodes'),
        'active_primary_shards' => t('Active primary shards'),
        'active_shards' => t('Active shards'),
        'relocating_shards' => t('Relocating shards'),
        'initializing_shards' => t('Initializing shards'),
        'unassigned_shards' => t('Unassigned shards'),
        'delayed_unassigned_shards' => t('Delayed unassigned shards'),
        'number_of_pending_tasks' => t('Number of pending tasks'),
        'number_of_in_flight_fetch' => t('Number of in-flight fetch'),
        'task_max_waiting_in_queue_millis' => t(
          'Task max waiting in queue millis'
        ),
        'active_shards_percent_as_number' => t(
          'Active shards percent as number'
        ),
      ];

      foreach ($cluster_status['health'] as $health_key => $health_value) {
        $row = [];
        $row[] = ['data' => $cluster_health_mapping[$health_key]];
        $row[] = ['data' => ($health_value === FALSE ? 'False' : $health_value)];
        $cluster_health_rows[] = $row;
      }
    }

    $output['cluster_statistics_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => t('Cluster statistics'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#attributes' => [],
    ];

    $output['cluster_statistics_wrapper']['nodes'] = [
      '#theme' => 'table',
      '#header' => [
        ['data' => t('Node name')],
        ['data' => t('Documents')],
        ['data' => t('Size')],
      ],
      '#rows' => $node_rows,
      '#attributes' => [],
    ];

    $output['cluster_statistics_wrapper']['cluster_statistics'] = [
      '#theme' => 'table',
      '#header' => [
        ['data' => t('Total'), 'colspan' => 6],
      ],
      '#rows' => $cluster_statistics_rows,
      '#attributes' => ['class' => ['admin-elasticsearch-statistics']],
    ];

    $output['cluster_health'] = [
      '#theme' => 'table',
      '#header' => [
        ['data' => t('Cluster Health'), 'colspan' => 2],
      ],
      '#rows' => $cluster_health_rows,
      '#attributes' => ['class' => ['admin-elasticsearch-health']],
    ];

    return $output;
  }

}
