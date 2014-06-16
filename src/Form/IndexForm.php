<?php

/**
 * @file
 * Contains \Drupal\elasticsearch_connector\ClusterForm.
 */

namespace Drupal\elasticsearch;

use Drupal\Core\Entity\EntityForm;
use Drupal\Component\Utility\String;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Url;
use Drupal\elasticsearch\Entity\Index;
use Elasticsearch\Common\Exceptions\Curl\CouldNotResolveHostException;

/**
 * Form controller for node type forms.
 */
class IndexForm extends EntityForm {
  /**
   * {@inheritdoc}
   */
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
    // TODO: Handle the validation of the elements.
    parent::validate($form, $form_state);

    $indices = $this->entity;

    $indices_from_form = entity_create('elasticsearch_cluster_indices', $form_state['values']);

    if (!preg_match('/^[a-z][a-z0-9_]*$/i', $form_state['values']['index_name'])) {
      form_set_error('index_name', t('Enter an index name that begins with a letter and contains only letters, numbers, and underscores.'));
    }

    if (!is_numeric($values['num_of_shards']) || $form_state['values']['num_of_shards'] < 1) {
      form_set_error('num_of_shards', t('Invalid number of shards.'));
    }

    if (!is_numeric($form_state['values']['num_of_replica'])) {
      form_set_error('num_of_replica', t('Invalid number of replica.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  //TODO
  public function save(array $form, array &$form_state) {
    
  }
}
