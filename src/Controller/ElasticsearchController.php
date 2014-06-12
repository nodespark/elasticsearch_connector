<?php

namespace Drupal\elasticsearch\Controller;

use Drupal\elasticsearch\Entity\Cluster;

/**
 * Example page controller.
 */
class ElasticSearchController {

  /**
  * Cluster status page callback.
  *
  * @return array
  *   A Drupal render array.
  */
  public function status() {
    $headers = array(
      array('data' => t('Cluster name')),
      array('data' => t('Status')),
      array('data' => t('Cluster Status')),
      array('data' => t('Operations')),
    );

    $rows = array();

    $clusters = elasticsearch_clusters();
    foreach ($clusters as $cluster) {
      $cluster_info = getClusterInfo();
      $edit_link_title = ($cluster->export_type & EXPORT_IN_CODE) ? t('Override') : t('Edit');
      if ($cluster->type == 'Overridden') {
        $edit_link_title = $cluster->type;
      }

      // TODO: Remove theme() as per D8 API
      $operations = array(
        '#type'  => 'table',
        '#links' => array(
          array('title' => $edit_link_title, 'href' => 'admin/config/elasticsearch/clusters/' . $cluster->cluster_id . '/edit'),
          array('title' => t('Info'), 'href' => 'admin/config/elasticsearch/clusters/' . $cluster->cluster_id . '/info'),
          array('title' => t('Indices'), 'href' => 'admin/config/elasticsearch/clusters/' . $cluster->cluster_id . '/indices'),
          array('title' => t('Delete'), 'href' => 'admin/config/elasticsearch/clusters/' . $cluster->cluster_id . '/delete'),
        ),
        '#attributes' => array(
          'class' => array('links', 'inline'),
        ),
      );

      if (!empty($cluster_info['info']) && checkClusterStatus($cluster_info['info'])) {
        $info = $cluster_info['health']['status'];
      }
      else {
        $info = t('Not available');
      }

      $row = array();
      $row[] = $cluster->name;
      $row[] = (!empty($cluster->status) ? t('Active') : t('Inactive'));
      $row[] = $info;
      $row[] = drupal_render($operations);

      $rows[] = $row;
    }

    $output['elasticsearch']['table'] = array(
      '#theme' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#attributes' => array('class' => array('admin-elasticsearch-connector')),
    );

    return $output;
  }
}