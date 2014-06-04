<?php

/**
 * @file
 * Contains \Drupal\elasticsearch_connector\ClusterForm.
 */

namespace Drupal\elasticsearch_connector;

use Drupal\Core\Entity\EntityForm;
use Drupal\Component\Utility\String;
use Drupal\Core\Entity\EntityTypeInterface;

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

    $form['label'] = array(
      '#type' => 'textfield',
      '#title' => t('Title'),
      '#default_value' => $cluster->label(),
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
      // A menu's machine name cannot be changed.
      '#disabled' => !$cluster->isNew(),
    );

    $form['description'] = array(
      '#type' => 'textfield',
      '#title' => t('Administrative summary'),
      '#maxlength' => 512,
      '#default_value' => $cluster->description,
    );

    return parent::form($form, $form_state);
  }

  public function clusterNameExists() {

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

    $form_state['redirect_route'] = $this->entity->urlInfo('edit-form');
  }
}
