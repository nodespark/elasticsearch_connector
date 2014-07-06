<?php

namespace Drupal\elasticsearch\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Component\Utility\String;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Url;
use Drupal\elasticsearch\Entity\Cluster;
use Elasticsearch\Common\Exceptions\Curl\CouldNotResolveHostException;

/**
 * Provides a form for the Cluster entity.
 */
class ClusterForm extends EntityForm {
  /**
   * {@inheritdoc}
   */
  public function form(array $form, array &$form_state) {
    $form = parent::form($form, $form_state);
    // Get the entity and attach to the form state.
    $cluster = $form_state['entity'] = $this->getEntity();
    //$cluster = $this->entity;

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
        //'exists' => array($this, 'loadCluster'),
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

    // Added by Nick to avoid errors
    // @todo: Cleanup these things
    $cluster_info = "";
    $form_state_active = FALSE;

    if (isset($cluster->url)) {
      try {
        //$cluster_info = $cluster->getClusterInfo();
        $form_state_active = TRUE;
      }
      catch (\Exception $e) {
        drupal_set_message($e->getMessage(), 'error');
      }
    }

    $form['status_info'] = $this->clusterFormInfo($cluster_info, $form_state_active);

    // @todo : Find a better way to get the default Cluster. Most likely a variable
    //$default = Cluster::getDefaultCluster();
    $default = "";
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
      '#default_value' => isset($cluster->status) ? $cluster->status : Cluster::ELASTICSEARCH_STATUS_ACTIVE,
      '#options' => array(
        Cluster::ELASTICSEARCH_STATUS_ACTIVE    => t('Active'),
        Cluster::ELASTICSEARCH_STATUS_INACTIVE  => t('Inactive'),
      ),
      '#required' => TRUE,
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validate(array $form, array &$form_state) {
    // TODO: Handle the validation of the elements.
    parent::validate($form, $form_state);

    $cluster = $this->getEntity();
    $cluster_from_form = entity_create('elasticsearch_cluster', $form_state['values']);

    try {
      $cluster_info = $cluster_from_form->getClusterInfo($cluster);
      if (!isset($cluster_info['info']) || !Cluster::checkClusterStatus($cluster_info['info'])) {
        form_set_error('url', $form_state, t('Cannot connect to the cluster!'));
      }
    }
    catch (\Exception $e) {
      form_set_error('url', $form_state, t('Cannot connect to the cluster!'));
    }

    // Complain if we are removing the default.
    $default = Cluster::getDefaultCluster();
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
          'class' => array('admin-elasticsearch'),
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
          'class' => array('admin-elasticsearch'),
          'id'  => 'cluster-info'),
      );
    }
    else {
      $element['#type'] = 'markup';
      $element['#markup'] = '<div id="cluster-info">&nbsp;</div>';
    }

    return $element;
  }

  public function save(array $form, array &$form_state) {
    $cluster = $this->entity;
    //print_r($cluster);

    if (!$cluster->isNew()) {
      // TODO:
      // $this->submitOverviewForm($form, $form_state);
    }

    $status = $cluster->save();
    
    $edit_link = \Drupal::linkGenerator()->generateFromUrl($this->t('Edit'), $this->entity->urlInfo());
    if ($status == SAVED_UPDATED) {
      drupal_set_message(t('Cluster %label has been updated.', array('%label' => $cluster->label())));
    }
    else {
      drupal_set_message(t('Cluster %label has been added.', array('%label' => $cluster->label())));
    }

    $form_state['redirect_route'] = new Url('elasticsearch.clusters');
  }
}
