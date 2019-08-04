<?php

namespace Drupal\elasticsearch_connector\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\elasticsearch_connector\ClusterManager;
use Drupal\elasticsearch_connector\ElasticSearch\ClientManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\elasticsearch_connector\Entity\Cluster;

/**
 * Form controller for node type forms.
 */
class IndexForm extends EntityForm {

  /**
   * @var ClientManagerInterface
   */
  private $clientManager;

  /**
   * The entity manager.
   *
   * This object members must be set to anything other than private in order for
   * \Drupal\Core\DependencyInjection\DependencySerialization to be detected.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The cluster manager service.
   *
   * @var \Drupal\elasticsearch_connector\ClusterManager
   */
  protected $clusterManager;

  /**
   * Constructs an IndexForm object.
   *
   * @param \Drupal\Core\Entity\EntityManager|\Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity type manager.
   * @param ClientManagerInterface $client_manager
   */
  public function __construct(EntityTypeManagerInterface $entity_manager, ClientManagerInterface $client_manager, ClusterManager $cluster_manager) {
    // Setup object members.
    $this->entityTypeManager = $entity_manager;
    $this->clientManager = $client_manager;
    $this->clusterManager = $cluster_manager;
  }

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *
   * @return static
   */
  static public function create(ContainerInterface $container) {
    return new static (
      $container->get('entity_type.manager'),
      $container->get('elasticsearch_connector.client_manager'),
      $container->get('elasticsearch_connector.cluster_manager')
    );
  }

  /**
   * Get the entity manager.
   *
   * @return \Drupal\Core\Entity\EntityManager
   *   An instance of EntityManager.
   */
  protected function getEntityManager() {
    return $this->entityTypeManager;
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
    foreach (
      $this->getClusterStorage()
           ->loadMultiple() as $cluster_machine_name
    ) {
      $options[$cluster_machine_name->cluster_id] = $cluster_machine_name;
    }
    return $options;
  }

  /**
   * Get cluster field.
   *
   * @param string $field
   *   Field name.
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
   * @param string $id
   *   Cluster id.
   *
   * @return string
   *   Cluster url.
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
  public function form(array $form, FormStateInterface $form_state) {
    if ($form_state->isRebuilding()) {
      $this->entity = $this->buildEntity($form, $form_state);
    }

    $form = parent::form($form, $form_state);
    $form['#title'] = $this->t('Index');

    $this->buildEntityForm($form, $form_state);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntityForm(array &$form, FormStateInterface $form_state) {
    // TODO: Provide check and support for other index modules settings.
    // TODO: Provide support for the rest of the dynamic settings.
    // TODO: Make sure that on edit the static settings cannot be changed.
    // @see https://www.elastic.co/guide/en/elasticsearch/reference/current/index-modules.html
    $form['index'] = array(
      '#type' => 'value',
      '#value' => $this->entity,
    );

    $form['name'] = array(
      '#type' => 'textfield',
      '#title' => t('Index name'),
      '#required' => TRUE,
      '#default_value' => '',
      '#description' => t('Enter the index name.'),
      '#weight' => 1,
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
      '#disabled' => !empty($this->entity->index_id),
      '#weight' => 2,
    );

    // Here server refers to the elasticsearch cluster.
    $form['server'] = array(
      '#type' => 'radios',
      '#title' => $this->t('Server'),
      '#default_value' => !empty($this->entity->server) ? $this->entity->server : $this->clusterManager->getDefaultCluster(),
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
      '#weight' => 3,
    );

    $form['num_of_replica'] = array(
      '#type' => 'textfield',
      '#title' => t('Number of replica'),
      '#default_value' => 1,
      '#description' => t('Enter the number of shards replicas.'),
      '#weight' => 4,
    );

    $form['codec'] = array(
      '#type' => 'select',
      '#title' => t('Codec'),
      '#default_value' => (!empty($this->entity->codec) ? $this->entity->codec : 'default'),
      '#description' => t('Select compression for stored data. Defaults to: LZ4.'),
      '#options' => array(
        'default' => 'LZ4',
        'best_compression' => 'DEFLATE',
      ),
      '#weight' => 5,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $values = $form_state->getValues();

    if (!preg_match('/^[a-z][a-z0-9_]*$/i', $values['index_id'])) {
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
  public function save(array $form, FormStateInterface $form_state) {
    $cluster = $this->entityTypeManager->getStorage('elasticsearch_cluster')->load($this->entity->server);
    $client = $this->clientManager->getClientForCluster($cluster);

    $index_params['index'] = $this->entity->index_id;
    $index_params['body']['settings']['number_of_shards'] = $form_state->getValue('num_of_shards');
    $index_params['body']['settings']['number_of_replicas'] = $form_state->getValue('num_of_replica');
    $index_params['body']['settings']['codec'] = $form_state->getValue('codec');

    try {
      $response = $client->indices()->create($index_params);
      if ($client->CheckResponseAck($response)) {
        $this->messenger()->addMessage(
          t(
            'The index %index having id %index_id has been successfully created.',
            array(
              '%index' => $form_state->getValue('name'),
              '%index_id' => $form_state->getValue('index_id'),
            )
          )
        );
      }
      else {
        $this->messenger()->addError(
          t(
            'Fail to create the index %index having id @index_id',
            array(
              '%index' => $form_state->getValue('name'),
              '@index_id' => $form_state->getValue('index_id'),
            )
          )
        );
      }

      parent::save($form, $form_state);

      $this->messenger()->addMessage(t('Index %label has been added.', array('%label' => $this->entity->label())));

      $form_state->setRedirect('elasticsearch_connector.config_entity.list');
    }
    catch (\Exception $e) {
      $this->messenger()->addError($e->getMessage());
    }
  }

}
