<?php

/**
 * @file
 * Contains \Drupal\elasticsearch_connector\Form\ClusterDeleteForm.
 */

namespace Drupal\elasticsearch_connector\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\elasticsearch_connector\Entity\Cluster;
use Drupal\elasticsearch_connector\Entity\Index;

/**
 * Defines a confirmation form for deletion of a custom menu.
 */
class ClusterDeleteForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return t('Are you sure you want to delete the cluster %title?', array('%title' => $this->entity->label()));
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('Deleting a cluster will disable all its indexes and their searches.');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $storage = \Drupal::entityManager()->getStorage('elasticsearch_index');
    $storage->loadByProperties(array('server' => $this->entity->cluster_id));

    // TODO: handle indices linked to the cluster being deleted.
    if ($this->entity->id() == Cluster::getDefaultCluster()) {
      drupal_set_message($this->t('The cluster %title cannot be deleted as it is set as the default cluster.', array('%title' => $this->entity->label())), 'error');
    }
    else {
      $this->entity->delete();
      drupal_set_message($this->t('The cluster %title has been deleted.', array('%title' => $this->entity->label())));
      $form_state->setRedirect('elasticsearch_connector.clusters');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('elasticsearch_connector.clusters', array('elasticsearch_cluster' => $this->entity->id()));
  }

}
