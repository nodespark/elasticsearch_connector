<?php

namespace Drupal\elasticsearch\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\elasticsearch\Entity\Cluster;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form for the Cluster entity.
 */
class ClusterForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    if ($form_state->isRebuilding()) {
      $this->entity = $this->buildEntity($form, $form_state);
    }
    $form = parent::form($form, $form_state);
    // Get the entity and attach to the form state.
    $cluster = $this->getEntity();

    if ($this->operation == 'edit') {
      $form['#title'] = $this->t('Edit Elasticsearch Cluster @label', array('@label' => $cluster->label()));
    }
    else {
      $form['#title'] = $this->t('Add Elasticsearch Cluster');
    } 
    
    $this->buildEntityForm($form, $form_state, $cluster);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntityForm(array &$form, FormStateInterface $form_state, Cluster $cluster) {
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
        'exists' => '\Drupal\elasticsearch\Entity\Cluster::load',
        'source' => array('name'),
        'replace_pattern' => '[^a-z0-9_]+',
        'replace' => '_',
      ),
      '#required' => TRUE,
      '#disabled' => !empty($cluster->cluster_id),
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

    $cluster_info = "";
    $form_state_active = FALSE;

    if (isset($cluster->url)) {
      try {
        $cluster_info = $cluster->getClusterInfo($cluster);
        $form_state_active = TRUE;
      }
      catch (\Exception $e) {
        drupal_set_message($e->getMessage(), 'error');
      }
    }

    $form['status_info'] = $this->clusterFormInfo($cluster_info, $form_state_active);

    $default = Cluster::getDefaultCluster();
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

  }
  /**
   * {@inheritdoc}
   */
  public function validate(array $form, FormStateInterface $form_state) {
    parent::validate($form, $form_state);

    $values = $form_state->getValues();

    /** @var Cluster $cluster_from_form */
    $cluster_from_form = entity_create('elasticsearch_cluster', $values);
    try {
      $cluster_info = Cluster::getClusterInfo($cluster_from_form);
      if (!isset($cluster_info['info']) || !Cluster::checkClusterStatus($cluster_info['info'])) {
        $form_state->setErrorByName('url', t('Cannot connect to the cluster!'));
      }
    }
    catch (\Exception $e) {
      $form_state->setErrorByName('url', t('Cannot connect to the cluster!'));
    }

    // Complain if we are removing the default.
    $default = Cluster::getDefaultCluster();
    if (empty($default) && !$values['default']) {
      $default = Cluster::setDefaultCluster($values['cluster_id']);
    }
    if ($values['default']) {
      $default = Cluster::setDefaultCluster($values['cluster_id']);
    }
    if ($values['default'] == 0 && !empty($default) && $default == $values['cluster_id']) {
      drupal_set_message(
        t('There must be a default connection. %name is still the default connection.'
            . 'Please change the default setting on the cluster you wish to set as default.',
            array(
            '%name' => $values['name'])
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
    $element = array();

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

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $cluster = $this->entity;
    
    $status = $cluster->save();
    
    if ($status == SAVED_UPDATED) {
      drupal_set_message(t('Cluster %label has been updated.', array('%label' => $cluster->label())));
    }
    else {
      drupal_set_message(t('Cluster %label has been added.', array('%label' => $cluster->label())));
    }

    $form_state->setRedirect('elasticsearch.clusters');
  }
}
