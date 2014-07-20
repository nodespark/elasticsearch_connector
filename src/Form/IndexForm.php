<?php

/**
 * @file
 * Contains \Drupal\elasticsearch\Form\IndexForm.
 */

namespace Drupal\elasticsearch\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Component\Utility\String;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Url;
use Drupal\Core\Entity\Entity;
use Drupal\elasticsearch\Entity\Cluster;
use Elasticsearch\Common\Exceptions\Curl\CouldNotResolveHostException;

/**
 * Form controller for node type forms.
 */
class IndexForm extends EntityForm {

  public function form(array $form, array &$form_state) {
    $cluster = $this->entity;

    if ($this->operation == 'edit') {
      // TODO: Handle edit!
    }

    $form['#cluster'] = $cluster;

    $form['index_name'] = array(
      '#type' => 'textfield',
      '#title' => t('Index name'),
      '#required' => TRUE,
      '#default_value' => '',
      '#description' => t('Enter the index name.')
    );

    $form['num_of_shards'] = array(
      '#type' => 'textfield',
      '#title' => t('Number of shards'),
      '#required' => TRUE,
      '#default_value' => '',
      '#description' => t('Enter the number of shards for the index.')
    );

    $form['num_of_replica'] = array(
      '#type' => 'textfield',
      '#title' => t('Number of replica'),
      '#default_value' => '',
      '#description' => t('Enter the number of shards replicas.')
    );
    return parent::form($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validate(array $form, array &$form_state) {
    parent::validate($form, $form_state);

    $indices = $this->entity;

    $indices_from_form = entity_create('elasticsearch_cluster_index', $form_state['values']);
    
    if (!preg_match('/^[a-z][a-z0-9_]*$/i', $form_state['values']['index_name'])) {
      form_set_error('index_name', t('Enter an index name that begins with a letter and contains only letters, numbers, and underscores.'));
    }

    if (!is_numeric($form_state['values']['num_of_shards']) || $form_state['values']['num_of_shards'] < 1) {
      form_set_error('num_of_shards', t('Invalid number of shards.'));
    }

    if (!is_numeric($form_state['values']['num_of_replica'])) {
      form_set_error('num_of_replica', t('Invalid number of replica.'));
    }
  }

public function submit(array $form, array &$form_state) {
  $values = $form_state['values'];
  $cluster = $form['#cluster'];
  //print_r($cluster);
  $client = Cluster::getClusterByUrls($cluster);
  if ($client) {
    try {
      $index_params['index'] = $values['index_name'];
      $index_params['body']['settings']['number_of_shards']   = $values['num_of_shards'];
      $index_params['body']['settings']['number_of_replicas'] = $values['num_of_replica'];
      $response = $client->indices()->create($index_params);
      if (elasticsearch_check_response_ack($response)) {
        drupal_set_message(t('The index %index has been successfully created.', array('%index' => $values['index_name'])));
      }
      else {
        drupal_set_message(t('Fail to create the index %index', array('%index' => $values['index_name'])), 'error');
      }

      // If the form has been opened in dialog, close the window if it was
      // setup to do so.
      if (elasticsearch_in_dialog() && elasticsearch_close_on_submit()) {
        elasticsearch_close_on_redirect($cluster->cluster_id, $values['index_name']);
      }
    }
    catch (Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
    }
  }
  return parent::submit($form, $form_state);
}

  public function save(array $form, array &$form_state) {
    $indices = $this->entity;
    
    $status = $indices->save();

    //$edit_link = \Drupal::linkGenerator()->generateFromUrl($this->t('Edit'), $this->entity->urlInfo());
    if ($status == SAVED_UPDATED) {
      drupal_set_message(t('Cluster %label has been updated.', array('%label' => $indices->label())));
    }
    else {
      drupal_set_message(t('Cluster %label has been added.', array('%label' => $indices->label())));
    }

    $form_state['redirect_route'] = new Url('elasticsearch.clusters');
  }
}
