<?php

/**
 * @file
 * Contains \Drupal\elasticsearch_connector\Form\IndexDeleteForm.
 */

namespace Drupal\elasticsearch_connector\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\elasticsearch_connector\ElasticSearch\ClientManager;
use Drupal\elasticsearch_connector\Entity\Cluster;
use Drupal\Core\Url;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a confirmation form for deletion of a custom menu.
 */
class IndexDeleteForm extends EntityConfirmFormBase {

  /**
   * @var ClientManager
   */
  private $clientManager;

  /**
   * ElasticsearchController constructor.
   *
   * @param ClientManager $client_manager
   */
  public function __construct(ClientManager $client_manager) {
    $this->clientManager = $client_manager;
  }

  static public function create(ContainerInterface $container) {
    return new static (
      $container->get('elasticsearch_connector.client_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return t('Are you sure you want to delete the index %title?', array('%title' => $this->entity->label()));
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var Cluster $cluster */
    $cluster = Cluster::load($this->entity->server);
    $client = $this->clientManager->getClientForCluster($cluster);
    try {
      if($client->indices()->exists(array('index' => $this->entity->index_id))) {
        $client->indices()->delete(['index' => $this->entity->index_id]);
      }
      $this->entity->delete();
      drupal_set_message($this->t('The index %title has been deleted.', array('%title' => $this->entity->label())));
      $form_state->setRedirect('elasticsearch_connector.clusters');
    }
    catch (Missing404Exception $e){
      // the index was not found, so just remove it anyway
      drupal_set_message($e->getMessage(), 'error');
    }
    catch (\Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
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
    return new Url('elasticsearch_connector.clusters');
  }

}
