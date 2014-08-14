<?php

/**
 * @file
 * Contains \Drupal\elasticsearch\Form\IndexForm.
 */

namespace Drupal\elasticsearch\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\elasticsearch\Entity\Index;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityManager;
use Drupal\elasticsearch\Entity\Cluster;

/**
 * Form controller for node type forms.
 */
class IndexForm extends EntityForm {

  // @todo: A lot of documentation is missing here!

  protected $entityManager;

  public function __construct(EntityManager $entity_manager) {
    // Setup object members.
    $this->entityManager = $entity_manager;
  }

  protected function getEntityManager() {
    return $this->entityManager;
  }

  protected function getClusterStorage() {
    return $this->getEntityManager()->getStorage('elasticsearch_cluster');
  }

  protected function getAllClusters() {
    $options = array();
    foreach ($this->getClusterStorage()->loadMultiple() as $cluster_machine_name) {
      $options[$cluster_machine_name->cluster_id] = $cluster_machine_name;
    }
    return $options;
  }

  protected function getClusterField($field) {
    $clusters = $this->getAllClusters();
    $options = array();
    foreach ($clusters as $cluster) {
      $options[$cluster->$field] = $cluster->$field;
    }
    return $options;
  }

  protected function getSelectedClusterUrl($id) {
    $result = NULL;
    $clusters = $this->getAllClusters();
    foreach ($clusters as $cluster) {
      if ($cluster->cluster_id == $id) {
        $result = $cluster->url;
      }
    }
    return $result;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager')
    );
  }


  public function form(array $form, FormStateInterface $form_state) {
    if (!empty($form_state['rebuild'])) {
      // Rebuild the entity with the form state values.
      $this->entity = $this->buildEntity($form, $form_state);
    }

    $form = parent::form($form, $form_state);

    $index = $form_state['entity'] = $this->getEntity();

    if ($this->operation == 'edit') {
      $form['#title'] = $this->t('Edit Index @label', array('@label' => $index->label()));
    }
    else {
      $form['#title'] = $this->t('Index');
    } 
    
    $this->buildEntityForm($form, $form_state, $index);
    return $form;
  }

  public function buildEntityForm(array &$form, FormStateInterface $form_state, Index $index) {
    $form['index'] = array(
      '#type'  => 'value',
      '#value' => $index,
    );

    $form['name'] = array(
      '#type' => 'textfield',
      '#title' => t('Index name'),
      '#required' => TRUE,
      '#default_value' => empty($index->name) ? '' : $index->name,
      '#description' => t('Enter the index name.')
    );

    $form['index_id'] = array(
      '#type' => 'machine_name',
      '#title' => t('Index id'),
      '#default_value' => !empty($index->index_id) ? $index->index_id : '',
      '#maxlength' => 125,
      '#description' => t('Unique, machine-readable identifier for this Index'),
      '#machine_name' => array(
        'exists' => '\Drupal\elasticsearch\Entity\Index::load',
        'source' => array('name'),
        'replace_pattern' => '[^a-z0-9_]+',
        'replace' => '_',
      ),
      '#required' => TRUE,
      '#disabled' => !empty($index->index_id),
    );

    $form['server'] = array(
      '#type' => 'radios',
      '#title' => $this->t('Server'),
      '#default_value' => empty($index->server) ? $index->server : '',
      '#description' => $this->t('Select the server this index should reside on. Index can not be enabled without connection to valid server.'),
      '#options' => $this->getClusterField('cluster_id'),
      '#weight' => 9,
      '#required' => TRUE,
    );

    $form['num_of_shards'] = array(
      '#type' => 'textfield',
      '#title' => t('Number of shards'),
      '#required' => TRUE,
      '#default_value' => empty($index->num_of_shards) ? '' : $index->num_of_shards,
      '#description' => t('Enter the number of shards for the index.')
    );

    $form['num_of_replica'] = array(
      '#type' => 'textfield',
      '#title' => t('Number of replica'),
      '#default_value' => empty($index->num_of_replica) ? '' : $index->num_of_replica,
      '#description' => t('Enter the number of shards replicas.')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate(array $form, FormStateInterface $form_state) {
    parent::validate($form, $form_state);

    // @todo form_set_error is deprecated
    if (!preg_match('/^[a-z][a-z0-9_]*$/i', $form_state['values']['name'])) {
      form_set_error('name', t('Enter an index name that begins with a letter and contains only letters, numbers, and underscores.'));
    }

    if (!is_numeric($form_state['values']['num_of_shards']) || $form_state['values']['num_of_shards'] < 1) {
      form_set_error('num_of_shards', t('Invalid number of shards.'));
    }

    if (!is_numeric($form_state['values']['num_of_replica'])) {
      form_set_error('num_of_replica', t('Invalid number of replica.'));
    }
  }

  public function submit(array $form, FormStateInterface $form_state) {
    $values = $form_state['values'];

    $cluster_url = self::getSelectedClusterUrl($form_state['values']['server']);

    $client = Cluster::getClusterByUrls(array($cluster_url));
    if ($client) {
      try {
        $index_params['index'] = $values['name'];
        $index_params['body']['settings']['number_of_shards']   = $values['num_of_shards'];
        $index_params['body']['settings']['number_of_replicas'] = $values['num_of_replica'];
        $index_params['body']['settings']['cluster_machine_name'] = $values['server'];
        $response = $client->indices()->create($index_params);
        // @todo: the check response ack function does not exist
        if (Cluster::elasticsearchCheckResponseAck($response)) {
          drupal_set_message(t('The index %index has been successfully created.', array('%index' => $values['name'])));
        }
        else {
          drupal_set_message(t('Fail to create the index %index', array('%index' => $values['name'])), 'error');
        }
      }
      catch (\Exception $e) {
        drupal_set_message($e->getMessage(), 'error');
      }
    }
    return parent::submit($form, $form_state);
  }

  // @TODO
  public function save(array $form, FormStateInterface $form_state) {
    $index = $this->entity;
    
    $status = $index->save();

    if ($status == SAVED_UPDATED) {
      drupal_set_message(t('Index %label has been updated.', array('%label' => $index->label())));
    }
    else {
      drupal_set_message(t('Index %label has been added.', array('%label' => $index->label())));
    }

    $form_state->setRedirect('elasticsearch.clusters');
  }
}
