<?php

namespace Drupal\elasticsearch_connector\Form;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\elasticsearch_connector\ClusterManager;
use Drupal\elasticsearch_connector\ElasticSearch\ClientManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for node type forms.
 */
class IndexForm extends EntityForm {

  /**
   * The client manager service.
   *
   * @var \Drupal\elasticsearch_connector\ElasticSearch\ClientManagerInterface
   */
  private $clientManager;

  /**
   * The entity manager.
   *
   * This object members must be set to anything other than private in order for
   * \Drupal\Core\DependencyInjection\DependencySerialization to be detected.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity type manager.
   * @param \Drupal\elasticsearch_connector\ElasticSearch\ClientManagerInterface $client_manager
   *   The client manager.
   * @param \Drupal\elasticsearch_connector\ClusterManager $cluster_manager
   *   The cluster manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_manager, ClientManagerInterface $client_manager, ClusterManager $cluster_manager) {
    // Setup object members.
    $this->entityTypeManager = $entity_manager;
    $this->clientManager = $client_manager;
    $this->clusterManager = $cluster_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('elasticsearch_connector.client_manager'),
      $container->get('elasticsearch_connector.cluster_manager')
    );
  }

  /**
   * Gets the entity manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   An instance of EntityManager.
   */
  protected function getEntityManager() {
    return $this->entityTypeManager;
  }

  /**
   * Gets the cluster storage controller.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   An instance of EntityStorageInterface.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getClusterStorage() {
    return $this->getEntityManager()->getStorage('elasticsearch_cluster');
  }

  /**
   * Gets the index storage controller.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   An instance of EntityStorageInterface.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getIndexStorage() {
    return $this->getEntityManager()->getStorage('elasticsearch_index');
  }

  /**
   * Gets all clusters.
   *
   * @return array
   *   All clusters
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getAllClusters() {
    $options = [];
    foreach (
      $this->getClusterStorage()
        ->loadMultiple() as $cluster_machine_name
    ) {
      $options[$cluster_machine_name->cluster_id] = $cluster_machine_name;
    }
    return $options;
  }

  /**
   * Gets cluster field.
   *
   * @param string $field
   *   Field name.
   *
   * @return array
   *   All clusters' fields.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getClusterField($field) {
    $clusters = $this->getAllClusters();
    $options = [];
    foreach ($clusters as $cluster) {
      $options[$cluster->$field] = $cluster->$field;
    }
    return $options;
  }

  /**
   * Returns url of the selected cluster.
   *
   * @param string $id
   *   Cluster id.
   *
   * @return string
   *   Cluster url.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
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
   * Builds entity form.
   *
   * @param array $form
   *   Form parameter.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state parameter.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function buildEntityForm(array &$form, FormStateInterface $form_state) {
    // TODO: Provide check and support for other index modules settings.
    // TODO: Provide support for the rest of the dynamic settings.
    // TODO: Make sure that on edit the static settings cannot be changed.
    // @see https://www.elastic.co/guide/en/elasticsearch/reference/current/index-modules.html
    $form['index'] = [
      '#type' => 'value',
      '#value' => $this->entity,
    ];

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => t('Index name'),
      '#required' => TRUE,
      '#default_value' => '',
      '#description' => t('Enter the index name.'),
      '#weight' => 1,
    ];

    $form['index_id'] = [
      '#type' => 'machine_name',
      '#title' => t('Index id'),
      '#default_value' => '',
      '#maxlength' => 125,
      '#description' => t('Unique, machine-readable identifier for this Index'),
      '#machine_name' => [
        'exists' => [$this->getIndexStorage(), 'load'],
        'source' => ['name'],
        'replace_pattern' => '[^a-z0-9_]+',
        'replace' => '_',
      ],
      '#required' => TRUE,
      '#disabled' => !empty($this->entity->index_id),
      '#weight' => 2,
    ];

    // Here server refers to the elasticsearch cluster.
    $form['server'] = [
      '#type' => 'radios',
      '#title' => $this->t('Server'),
      '#default_value' => !empty($this->entity->server) ? $this->entity->server : $this->clusterManager->getDefaultCluster(),
      '#description' => $this->t('Select the server this index should reside on. Index can not be enabled without connection to valid server.'),
      '#options' => $this->getClusterField('cluster_id'),
      '#weight' => 9,
      '#required' => TRUE,
    ];

    $form['num_of_shards'] = [
      '#type' => 'textfield',
      '#title' => t('Number of shards'),
      '#required' => TRUE,
      '#default_value' => 5,
      '#description' => t('Enter the number of shards for the index.'),
      '#weight' => 3,
    ];

    $form['num_of_replica'] = [
      '#type' => 'textfield',
      '#title' => t('Number of replica'),
      '#default_value' => 1,
      '#description' => t('Enter the number of shards replicas.'),
      '#weight' => 4,
    ];

    $form['codec'] = [
      '#type' => 'select',
      '#title' => t('Codec'),
      '#default_value' => (!empty($this->entity->codec) ? $this->entity->codec : 'default'),
      '#description' => t('Select compression for stored data. Defaults to: LZ4.'),
      '#options' => [
        'default' => 'LZ4',
        'best_compression' => 'DEFLATE',
      ],
      '#weight' => 5,
    ];
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
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function save(array $form, FormStateInterface $form_state) {
    $cluster = $this->entityTypeManager->getStorage('elasticsearch_cluster')
      ->load($this->entity->server);
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
            [
              '%index' => $form_state->getValue('name'),
              '%index_id' => $form_state->getValue('index_id'),
            ]
          )
        );
      }
      else {
        $this->messenger()->addError(
          t(
            'Fail to create the index %index having id @index_id',
            [
              '%index' => $form_state->getValue('name'),
              '@index_id' => $form_state->getValue('index_id'),
            ]
          )
        );
      }

      parent::save($form, $form_state);

      $this->messenger()->addMessage(t('Index %label has been added.', ['%label' => $this->entity->label()]));

      $form_state->setRedirect('elasticsearch_connector.config_entity.list');
    }
    catch (\Exception $e) {
      $this->messenger()->addError($e->getMessage());
    }
  }

}
