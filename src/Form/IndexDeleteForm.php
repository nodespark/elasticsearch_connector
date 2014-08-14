<?php

/**
 * @file
 * Contains \Drupal\elasticsearch\Form\IndexDeleteForm.
 */

namespace Drupal\elasticsearch\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\elasticsearch\Entity\Cluster;

/**
 * Defines a confirmation form for deletion of a custom menu.
 */
class IndexDeleteForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return t('Are you sure you want to delete the index %title?', array('%title' => $this->entity->label()));
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array $form, FormStateInterface $form_state) {
    $cluster = Cluster::loadCluster($this->entity->server);
    $client = Cluster::getClusterByUrls(array($cluster->url));
    if ($client) {
      try {
        $client->indices()->delete(array('index' => $this->entity->index_id));
        $this->entity->delete();
        drupal_set_message($this->t('The index %title has been deleted.', array('%title' => $this->entity->label())));
        $form_state->setRedirect('elasticsearch.clusters');
      }
      catch (\Exception $e) {
        drupal_set_message($e->getMessage(), 'error');
      }
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
    return $this->entity->urlInfo('canonical');
  }
}
