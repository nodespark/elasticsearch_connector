<?php

namespace Drupal\elasticsearch_connector\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\elasticsearch_connector\ElasticSearch\ClientManagerInterface;
use Drupal\elasticsearch_connector\Entity\Cluster;
use Drupal\Core\Url;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a confirmation form for deletion of a custom menu.
 */
class IndexDeleteForm extends EntityConfirmFormBase {

  /**
   * @var ClientManagerInterface
   */
  private $clientManager;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * ElasticsearchController constructor.
   *
   * @param ClientManagerInterface $client_manager
   *   The client manager service.
   * @param EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(ClientManagerInterface $client_manager, EntityTypeManagerInterface $entity_type_manager) {
    $this->clientManager = $client_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   *
   */
  static public function create(ContainerInterface $container) {
    return new static (
      $container->get('elasticsearch_connector.client_manager'),
      $container->get('entity_type.manager'),
      $container->get('elasticsearch_connector.cluster_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the index %title?', array('%title' => $this->entity->label()));
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var Cluster $cluster */
    $cluster = $this->entityTypeManager->getStorage('elasticsearch_cluster')->load($this->entity->server);
    $client = $this->clientManager->getClientForCluster($cluster);
    try {
      if ($client->indices()
                 ->exists(array('index' => $this->entity->index_id))
      ) {
        $client->indices()->delete(['index' => $this->entity->index_id]);
      }
      $this->entity->delete();
      $this->messenger()->addMessage($this->t('The index %title has been deleted.', array('%title' => $this->entity->label())));
      $form_state->setRedirect('elasticsearch_connector.config_entity.list');
    }
    catch (Missing404Exception $e) {
      // The index was not found, so just remove it anyway.
      $this->messenger()->addError($e->getMessage());
    }
    catch (\Exception $e) {
      $this->messenger()->addError($e->getMessage());
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
    return new Url('elasticsearch_connector.config_entity.list');
  }

}
