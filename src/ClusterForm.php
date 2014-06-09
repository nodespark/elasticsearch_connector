<?php

/**
 * @file
 * Contains \Drupal\elasticsearch_connector\ClusterForm.
 */

namespace Drupal\elasticsearch_connector;

use Drupal\Core\Entity\EntityForm;
use Drupal\Component\Utility\String;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Url;
use Drupal\elasticsearch_connector\Entity\Cluster;

/**
 * Form controller for node type forms.
 */
class ClusterForm extends EntityForm {
  /**
   * {@inheritdoc}
   */
  public function form(array $form, array &$form_state) {
    $cluster = $this->entity;

    if ($this->operation == 'edit') {
      // TODO: Handle edit!
    }

    $form['cluster'] = array(
      '#type'  => 'value',
      '#value' => $cluster,
    );

    $form['name'] = array(
      '#type' => 'textfield',
      '#title' => t('Cluster name'),
      '#default_value' => empty($cluster->name) ? '' : $cluster->name,
      '#description' => t('Example: ElasticaCluster'),
      '#required' => TRUE,
    );

    $form['cluster_id'] = array(
      '#type' => 'machine_name',
      '#title' => t('Cluster id'),
      '#default_value' => !empty($cluster->cluster_id) ? $cluster->cluster_id : '',
      '#maxlength' => 125,
      '#description' => t('Unique, machine-readable identifier for this Elasticsearch environment.'),
      '#machine_name' => array(
        'exists' => array($this, 'clusterNameExists'),
        'source' => array('name'),
        'replace_pattern' => '[^a-z0-9-]+',
        'replace' => '_',
      ),
      '#required' => TRUE,
    );

    $form['url'] = array(
      '#type' => 'textfield',
      '#title' => t('Server URL'),
      '#default_value' => !empty($cluster->url) ? $cluster->url : '',
      '#description' => t('Enter the URL of a node in the cluster. ' .
          'All nodes will be automatically discover. ' .
          'Example: http://localhost:9200'),
      '#required' => TRUE,
      '#ajax' => array(
        'method' => 'replace',
        'callback' => 'elasticsearch_connector_edit_cluster_ajax',
        'effect' => 'fade',
        'event'  => 'blur'
      ),
    );

//     $cluster_info = NULL;
//     $form_state_active = FALSE;
//     if (isset($form_state['values'])) {
//       $values = (object)$form_state['values'];
//       if (!empty($values->url)) {
//         $cluster_info = elasticsearch_connector_get_cluster_info($values);
//         $form_state_active = TRUE;
//       }
//     }
//     elseif (isset($cluster->url)) {
//       $cluster_info = elasticsearch_connector_get_cluster_info($cluster);
//       $form_state_active = TRUE;
//     }

    $form['status_info'] = $this->clusterFormInfo($cluster_info, $form_state_active);

    $default = elasticsearch_connector_get_default_connector();
    $form['default'] = array(
      '#type' => 'checkbox',
      '#title' => t('Make this cluster default connection'),
      '#description' => t('If no specific cluster connection specified the API will use the default connection.'),
      '#default_value' => (empty($default) || (!empty($cluster->cluster_id) && $cluster->cluster_id == $default)) ? '1' : '0',
    );

    $form['options'] = array(
      '#tree' => TRUE
    );

    $form['options']['multiple_nodes_connection'] = array(
      '#type' => 'checkbox',
      '#title' => t('Use multiple nodes connection'),
      '#description' => t('It will automatically discover all nodes and use them in the connection to the cluster. ' .
        'The Elasticsearch client can then randomise the query execution between nodes.'),
      '#default_value' => (!empty($cluster->options['multiple_nodes_connection']) ? 1 : 0),
    );

    $form['status'] = array(
      '#type' => 'radios',
      '#title' => t('Status'),
      '#default_value' => isset($cluster->status) ? $cluster->status : Cluster::STATUS_ACTIVE,
      '#options' => array(
        Cluster::STATUS_ACTIVE    => t('Active'),
        Cluster::STATUS_INACTIVE  => t('Inactive'),
      ),
      '#required' => TRUE,
    );

    return parent::form($form, $form_state);
  }

  protected function clusterFormInfo($cluster_info = NULL, $ajax = NULL) {
    $headers = array(
      array('data' => t('Cluster name')),
      array('data' => t('Status')),
      array('data' => t('Number of nodes')),
    );

    $rows = $element = array();

    if (isset($cluster_info['state'])) {
      $rows = array(array(
        $cluster_info['health']['cluster_name'],
        $cluster_info['health']['status'],
        $cluster_info['health']['number_of_nodes'],
      ));

      $element = array(
        '#theme' => 'table',
        '#header' => $headers,
        '#rows' => $rows,
        '#attributes' => array(
          'class' => array('admin-elasticsearch-connector'),
          'id'  => 'cluster-info'),
      );
    }
    elseif (!empty($ajax)) {
      $rows = array(array(
        t('Unknown'),
        t('Unavailable'),
        '',
      ));

      $element = array(
        '#theme' => 'table',
        '#header' => $headers,
        '#rows' => $rows,
        '#attributes' => array(
          'class' => array('admin-elasticsearch-connector'),
          'id'  => 'cluster-info'),
      );
    }
    else {
      $element['#type'] = 'markup';
      $element['#markup'] = '<div id="cluster-info">&nbsp;</div>';
    }

    return $element;
  }

  public function clusterNameExists() {
    // TODO: Implement cluster exists function!
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, array &$form_state) {
    $cluster = $this->entity;

    if (!$cluster->isNew()) {
      // TODO:
      // $this->submitOverviewForm($form, $form_state);
    }

    $status = $cluster->save();

    $edit_link = \Drupal::linkGenerator()->generateFromUrl($this->t('Edit'), $this->entity->urlInfo());
    if ($status == SAVED_UPDATED) {
      drupal_set_message(t('Cluster %label has been updated.', array('%label' => $cluster->label())));
      watchdog('elasticsearch_connector', 'Cluster %label has been updated.', array('%label' => $cluster->label()), WATCHDOG_NOTICE, $edit_link);
    }
    else {
      drupal_set_message(t('Cluster %label has been added.', array('%label' => $cluster->label())));
      watchdog('elasticsearch_connector', 'Cluster %label has been added.', array('%label' => $cluster->label()), WATCHDOG_NOTICE, $edit_link);
    }

    $form_state['redirect_route'] = new Url('elasticsearch_connector.clusters');
  }
}
