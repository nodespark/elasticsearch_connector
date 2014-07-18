<?php

namespace Drupal\elasticsearch\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Elasticsearch\Client;
use Drupal\Component\Utility\UrlHelper;

/**
 * Defines the search server configuration entity.
 *
 * @ConfigEntityType(
 *   id = "elasticsearch_cluster_index",
 *   label = @Translation("Elasticsearch Cluster Indices"),
 *   controllers = {
 *     "storage" = "Drupal\Core\Config\Entity\ConfigEntityStorage",
 *     "list_builder" = "Drupal\elasticsearch\Controller\IndexListBuilder",
 *     "form" = {
 *       "default" = "Drupal\elasticsearch\Form\IndexForm",
 *       "edit" = "Drupal\elasticsearch\Form\IndexForm",
 *       "delete" = "Drupal\elasticsearch\Form\IndexDeleteForm",
 *     },
 *   },
 *   admin_permission = "administer elasticsearch",
 *   config_prefix = "index",
 *   entity_keys = {
 *     "label" = "index_name",
 *     "shards" = "num_of_shards",
 *     "replica" = "num_of_replica"
 *   },
 *   links = {
 *     "canonical" = "elasticsearch.clusterindex_view",
 *     "add-form" = "elasticsearch.clusterindex_add",
 *     "delete-form" = "elasticsearch.clusterindex_delete",
 *   }
 * )
 */
class Index extends ConfigEntityBase {

  /**
   * {@inheritdoc}
   */
  public $index_name;

  public $num_of_shards;

  public $num_of_replica;

  /**
   * Display all indices in cluster.
   *
   * @param object
   * @return array
   */
  function elasticsearchClusterIndices($cluster) {
    echo 'hi';
    $headers = array(
      array('data' => t('Index name')),
      array('data' => t('Docs')),
      array('data' => t('Size')),
      array('data' => t('Operations')),
    );

    $rows = array();
    $cluster_info = getClusterInfo($cluster);
    $client = $cluster_info['client'];

    if ($client && !empty($cluster_info['info']) && checkClusterStatus($cluster_info['info'])) {
      $indices = $client->indices()->stats();
      foreach ($indices['indices'] as $index_name => $index_info) {
        $row = array();

        $operations = theme('links', array(
          'links' => array(
            array('title' => t('Aliases'), 'href' => 'admin/config/search/elasticsearch/clusters/' . $cluster->cluster_id . '/indices/' . $index_name . '/aliases'),
            array('title' => t('Delete'), 'href' => 'admin/config/search/elasticsearch/clusters/' . $cluster->cluster_id . '/indices/' . $index_name . '/delete'),
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
