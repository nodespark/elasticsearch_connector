<?php

namespace Drupal\elasticsearch;

use Drupal\Core\Entity\EntityForm;
use Drupal\Component\Utility\String;
use Drupal\Core\Entity\EntityTypeInterface;

class ClusterForm extends EntityForm {
  public function form(array $form, array &$form_state) {
    $cluster = $this->entity;

    $form['label'] = array(
      '#type' => 'textfield',
      '#title' => t('Title'),
      '#default_value' => $cluster->label(),
      '#description' => t('Example: ElasticaCluster'),
      '#required' => TRUE,
    );

    $form['id'] = array(
      '#type' => 'machine_name',
      '#title' => t('Cluster name'),
      '#default_value' => $cluster->id(),
      '#description' => t('A unique name for the cluster.'),
      '#machine_name' => array(
        'exists' => array($this, 'clusterNameExists'),
        'source' => array('label'),
        'replace_pattern' => '[^a-z0-9-]+',
        'replace' => '-',
      ),
      '#disabled' => !$cluster->isNew(),
      '#description' =>
        t('Unique, machine-readable identifier for this Elasticsearch environment.'),
    );

    $form['description'] = array(
      '#type' => 'textfield',
      '#title' => t('Administrative summary'),
      '#maxlength' => 512,
      '#default_value' => $cluster->description,
    );

    return parent::form($form, $form_state);
  }
