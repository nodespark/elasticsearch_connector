<?php

/**
 * @file
 * Contains \Drupal\elasticsearch_connector\Form\IndexForm.
 */

namespace Drupal\elasticsearch_connector\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\elasticsearch_connector\Entity\Index;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityManager;
use Drupal\elasticsearch_connector\Entity\Cluster;

/**
 * Form controller for node type forms.
 */
class IndexForm extends EntityForm {

    /**
   * The entity manager.
   *
   * This object members must be set to anything other than private in order for
   * \Drupal\Core\DependencyInjection\DependencySerialization to be detected.
   *
   * @var \Drupal\Core\Entity\EntityManager
   */
  protected $entityManager;

  /**
   * Constructs an IndexForm object.
   *
   * @param \Drupal\Core\Entity\EntityManager $entity_manager
   *   The entity manager.
   */
  public function __construct(EntityManager $entity_manager) {
    // Setup object members.
    $this->entityManager = $entity_manager;
  }

  /**
   * Get the entity manager.
   *
   * @return \Drupal\Core\Entity\EntityManager
   *   An instance of EntityManager.
   */
  protected function getEntityManager() {
    return $this->entityManager;
  }

  /**
   * Get the cluster storage controller.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   An instance of EntityStorageInterface.
   */
  protected function getClusterStorage() {
    return $this->getEntityManager()->getStorage('elasticsearch_cluster');
  }

  /**
   * Get the index storage controller.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   An instance of EntityStorageInterface.
   */
  protected function getIndexStorage() {
    return $this->getEntityManager()->getStorage('elasticsearch_index');
  }

  /**
   * Get all clusters.
   *
   * @return array
   *   All clusters
   */
  protected function getAllClusters() {
    $options = array();
    foreach ($this->getClusterStorage()->loadMultiple() as $cluster_machine_name) {
      $options[$cluster_machine_name->cluster_id] = $cluster_machine_name;
    }
    return $options;
  }

  /**
   * Get cluster field.
   *
   * @param string
   *   field name
   *  
   * @return array
   *   All clusters' fields.
   */
  protected function getClusterField($field) {
    $clusters = $this->getAllClusters();
    $options = array();
    foreach ($clusters as $cluster) {
      $options[$cluster->$field] = $cluster->$field;
    }
    return $options;
  }

  /**
   * Return url of the selected cluster.
   *
   * @param string
   *   cluster id
   *  
   * @return string
   *   cluster url
   */
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

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager')
    );
  }


  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    if ($form_state->isRebuilding()) {
      $this->entity = $this->buildEntity($form, $form_state);
    }

    $form = parent::form($form, $form_state);

    $index = $this->getEntity();

    $form['#title'] = $this->t('Index');
    
    $this->buildEntityForm($form, $form_state, $index);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntityForm(array &$form, FormStateInterface $form_state, Index $index) {
    $form['index'] = array(
      '#type'  => 'value',
      '#value' => $index,
    );

    $form['name'] = array(
      '#type' => 'textfield',
      '#title' => t('Index name'),
      '#required' => TRUE,
      '#default_value' => '',
      '#description' => t('Enter the index name.')
    );

    $form['index_id'] = array(
      '#type' => 'machine_name',
      '#title' => t('Index id'),
      '#default_value' => '',
      '#maxlength' => 125,
      '#description' => t('Unique, machine-readable identifier for this Index'),
      '#machine_name' => array(
        'exists' => array($this->getIndexStorage(), 'load'),
        'source' => array('name'),
        'replace_pattern' => '[^a-z0-9_]+',
        'replace' => '_',
      ),
      '#required' => TRUE,
      '#disabled' => !empty($index->index_id),
    );

    // Here server refers to the elasticsearch cluster.
    $form['server'] = array(
      '#type' => 'radios',
      '#title' => $this->t('Server'),
      '#default_value' => !empty($index->server) ? $index->server : Cluster::getDefaultCluster(),
      '#description' => $this->t('Select the server this index should reside on. Index can not be enabled without connection to valid server.'),
      '#options' => $this->getClusterField('cluster_id'),
      '#weight' => 9,
      '#required' => TRUE,
    );

    $form['num_of_shards'] = array(
      '#type' => 'textfield',
      '#title' => t('Number of shards'),
      '#required' => TRUE,
      '#default_value' => 5,
      '#description' => t('Enter the number of shards for the index.'),
    );

    $form['num_of_replica'] = array(
      '#type' => 'textfield',
      '#title' => t('Number of replica'),
      '#default_value' => 1,
      '#description' => t('Enter the number of shards replicas.'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate(array $form, FormStateInterface $form_state) {
    parent::validate($form, $form_state);

    $values = $form_state->getValues();

    if (!preg_match('/^[a-z][a-z0-9_]*$/i', $values['name'])) {
      $form_state->setErrorByName('name', t('Enter an index name that begins with a letter and contains only letters, numbers, and underscores.'));
    }

    if (!is_numeric($values['num_of_shards']) || $values['num_of_shards'] < 1) {
      $form_state->setErrorByName('num_of_shards', t('Invalid number of shards.'));
    }

    if (!is_numeric($values['num_of_replica'])) {
      $form_state->setErrorByName('num_of_replica', t('Invalid number of replica.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array $form, FormStateInterface $form_state) {
    $values = $values;

    $cluster_url = self::getSelectedClusterUrl($values['server']);

    $client = Cluster::getClientByUrls(array($cluster_url));
    $index_params = array();
    if ($client) {
      try {
        $index_params['index'] = $values['index_id'];
        $index_params['body']['settings']['number_of_shards']   = $values['num_of_shards'];
        $index_params['body']['settings']['number_of_replicas'] = $values['num_of_replica'];
        $index_params['body']['settings']['cluster_machine_name'] = $values['server'];

      }
      catch (\Exception $e) {
        drupal_set_message($e->getMessage(), 'error');
      }
      try {
        $response = $client->indices()->create($index_params);
        if (Cluster::elasticsearchCheckResponseAck($response)) {
          drupal_set_message(t('The index %index having id %index_id has been successfully created.',
            array('%index' => $values['name'], '%index_id' => $values['index_id'])));
        }
        else {
          drupal_set_message(t('Fail to create the index %index having id @index_id',
            array('%index' => $values['name'], '@index_id' => $values['index_id'])), 'error');
        }
      }
      catch (\Exception $e) {
        drupal_set_message($e->getMessage(), 'error');
      }
    }
    return parent::submit($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $index = $this->entity;
    $index->save();

    drupal_set_message(t('Index %label has been added.', array('%label' => $index->label())));

    $form_state->setRedirect('elasticsearch_connector.clusters');
  }
}
