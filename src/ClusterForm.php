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
use Elasticsearch\Common\Exceptions\Curl\CouldNotResolveHostException;

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
    );

    if (isset($cluster->url)) {
      try {
        $cluster_info = $cluster->getClusterInfo();
        $form_state_active = TRUE;
      }
      catch (\Exception $e) {
        drupal_set_message($e->getMessage(), 'error');
      }
    }

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

  /**
   * {@inheritdoc}
   */
  public function validate(array $form, array &$form_state) {
    // TODO: Handle the validation of the elements.
    parent::validate($form, $form_state);

    $cluster = $this->entity;

    $cluster_from_form = entity_create('elasticsearch_connector_cluster', $form_state['values']);

    try {
      $cluster_info = $cluster_from_form->getClusterInfo();
      if (!isset($cluster_info['info']) || !Cluster::checkStatus($cluster_info['info'])) {
        form_set_error('url', $form_state, t('Cannot connect to the cluster!'));
      }
    }
    catch (\Exception $e) {
      form_set_error('url', $form_state, t('Cannot connect to the cluster!'));
    }

    // Complain if we are removing the default.
    $default = Cluster::getDefaultConnector();
    if ($form_state['values']['default'] == 0 && !empty($default) && $default == $form_state['values']['cluster_id']) {
      drupal_set_message(
        t('There must be a default connection. %name is still the default connection.'
            . 'Please change the default setting on the cluster you wish to set as default.',
            array(
            '%name' => $form_state['values']['name'])
        ),
        'warning'
      );
    }
  }

  /**
   * Build the cluster info table for the edit page.
   *
   * @param string $cluster_info
   * @param string $ajax
   * @return Ambigous <multitype:, multitype:string multitype: multitype:string multitype:string   multitype:multitype:string The   , multitype:string multitype:string multitype:string   multitype:multitype:NULL   multitype:multitype:The  multitype:Ambigous <The, string, \Drupal\Component\Utility\mixed, unknown, \Drupal\Core\StringTranslation\FALSE, boolean>   >
   */
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
