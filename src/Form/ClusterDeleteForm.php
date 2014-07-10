<?php

/**
 * @file
 * Contains \Drupal\elasticsearch\Form\ClusterDeleteForm.
 */

namespace Drupal\elasticsearch\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Url;

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
  public function submit(array $form, array &$form_state) {
    $this->entity->delete();
    drupal_set_message($this->t('The cluster %title has been deleted.', array('%title' => $this->entity->label())));
    $form_state['redirect_route'] = $this->getCancelRoute();
  }

  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelRoute() {
    return $this->entity->urlInfo('canonical');
  }
}
