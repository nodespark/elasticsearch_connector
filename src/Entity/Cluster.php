<?php

namespace Drupal\elasticsearch\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Elasticsearch\Client;
use Drupal\Component\Utility\UrlHelper;

class Cluster extends ConfigEntityBase {

  // Active status
  const ELASTICSEARCH_STATUS_ACTIVE = 1;

  // Inactive status
  const ELASTICSEARCH_STATUS_INACTIVE = 0;

  // Cluster status
  const ELASTICSEARCH_CLUSTER_STATUS_OK = 200;

  /**
  * The cluster machine name.
  *
  * @var string
  */
  public $cluster_id;

  /**
   * The human-readable name of the cluster entity.
   *
   * @var string
   */
  public $name;

  /**
   * Status.
   *
   * @var string
   */
  public $status;

  /**
   * The cluster url.
   *
   * @var string
   */
  public $url;

  /**
   * Options of the cluster.
   * @var array
   */
  public $options;

  /**
   * The locked status of this cluster.
   *
   * @var bool
   */
  protected $locked = FALSE;

  /**
   * {@inheritdoc}
   */
  public function id() {
    return isset($this->cluster_id) ? $this->cluster_id : NULL;
  }

  /**
 * Get the default (cluster) used for elasticsearch.
 *
 * @return string
 */
  public function getDefaultCluster() {
    return \Drupal::state()->get('elasticsearch_get_default', '');
  }

  /**
   * Set the default (cluster) used for elasticsearch.
   *
   * @return string
   */
  public function setDefaultCluster($cluster_id) {
    return \Drupal::state()->set('elasticsearch_get_default', $cluster_id); 
  }

  /**
   * Return cluster info.
   * @return return array
   */
  public function getClustersInfo() {
    $result = FALSE;
    try {
      $client = $this->getClusterByUrls(array($this->url));
      if (!empty($client)) {
        $info = $client->info();
        $result['client'] = $client;
        $result['info'] = $result['state'] = $result['health'] = $result['stats'] = array();
        if (self::checkStatus($info)) {
          $result['info'] = $info;
          $result['state'] = $client->cluster()->state();
          $result['health'] = $client->cluster()->health();
          $result['stats'] = $client->nodes()->stats();
        }
      }
    }
    catch (Exception $e) {
      throw $e;
    }

    return $result;
  }

  /**
  *
  * @param object $cluster
  * @return array
  */
  function getClusterInfo($cluster) {
    elasticsearch_set_breadcrumb(array(
      l(t('Elasticsearch Clusters'), 'admin/config/elasticsearch/clusters'))
    );

    $cluster_status = getClustersInfo();
    $cluster_client = $cluster_status['client'];

    $node_rows = $cluster_statistics_rows = $cluster_health_rows = array();

    if (isset($cluster_client) && !empty($cluster_status['info']) && checkClusterStatus($cluster_status['info'])) {
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
   * Return the cluster object based on Cluster ID.
   *
   * @param string $cluster_id
   * @param boolean
   * @return \Elasticsearch\Client $client
   */
  protected function getClusterById($cluster_id = NULL) {
    if (!isset($cluster_id) && !empty(getDefaultCluster())) {
      $cluster_id = getDefaultCluster();
    }

    if (!empty($cluster_id)) {
      $client = FALSE;
      $cluster = loadCluster($cluster_id);
      if ($cluster) {
        $client = getClusterByUrls($cluster->url);
      }
    }

    return $client;
  }

  /**
   * We need to handle the case where url is and array of urls
   * @param string $url
   * @return
   */
  protected function getClusterByUrls($urls) {
    // TODO: Handle cluster connection. This should be accomplished if the setting is enabled.
    // If enabled, discover all the nodes in the cluster initialize the Pool connection.
    $this->valid_url($urls);

    $options = array(
      'hosts' => $urls,
    );

    \Drupal::moduleHandler()->alter('elasticsearch_load_library_options', $options);
    return new Client($options);
  }

  /**
   * Check if the status is OK.
   * @param array $status
   * @return bool
   */
  public function checkClusterStatus($status) {
    if (is_array($status) && $status['status'] == ELASTICSEARCH_CLUSTER_STATUS_OK) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
  * Load a cluster object from the database.
  *
  * @see ctools_export_load_object().
  *
  * @param string $cluster_id
  * @return object $cluster
  */
  public function loadCluster($cluster_id) {
    // TODO: Remove ctools
    ctools_include('export');
    $result = ctools_export_load_object('elasticsearch_cluster', 'names', array($cluster_id));
    if (isset($result[$cluster_id])) {
      if (isset($result[$cluster_id]->options) && !is_array($result[$cluster_id]->options)) {
        $result[$cluster_id]->options = unserialize($result[$cluster_id]->options);
      }
      return $result[$cluster_id];
    }
  }
}
