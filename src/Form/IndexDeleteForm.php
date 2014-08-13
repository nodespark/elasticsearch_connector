<?php

/**
 * @file
 * Contains \Drupal\elasticsearch\Form\IndexDeleteForm.
 */

namespace Drupal\elasticsearch\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

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
    $this->entity->delete();
    drupal_set_message($this->t('The index %title has been deleted.', array('%title' => $this->entity->label())));
    $form_state->setRedirect('elasticsearch.clusters');
  }

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
