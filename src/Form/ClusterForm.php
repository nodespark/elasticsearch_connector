<?php

namespace Drupal\elasticsearch_connector\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\elasticsearch_connector\ClusterManager;
use Drupal\elasticsearch_connector\ElasticSearch\ClientManagerInterface;
use Drupal\elasticsearch_connector\Entity\Cluster;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityStorageException;

/**
 * Provides a form for the Cluster entity.
 */
class ClusterForm extends EntityForm {

  /**
   * @var ClientManagerInterface
   */
  private $clientManager;

  /**
   * The cluster manager service.
   *
   * @var \Drupal\elasticsearch_connector\ClusterManager
   */
  protected $clusterManager;

  /**
   * ElasticsearchController constructor.
   *
   * @param ClientManagerInterface $client_manager
   *   The client manager.
   * @param \Drupal\elasticsearch_connector\ClusterManager $cluster_manager
   *   The cluster manager.
   */
  public function __construct(ClientManagerInterface $client_manager, ClusterManager $cluster_manager) {
    $this->clientManager = $client_manager;
    $this->clusterManager = $cluster_manager;
  }

  /**
   *
   */
  static public function create(ContainerInterface $container) {
    return new static (
      $container->get('elasticsearch_connector.client_manager'),
      $container->get('elasticsearch_connector.cluster_manager')
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
    if ($this->entity->isNew()) {
      $form['#title'] = $this->t('Add Elasticsearch Cluster');
    }
    else {
      $form['#title'] = $this->t(
        'Edit Elasticsearch Cluster @label',
        array('@label' => $this->entity->label())
      );
    }

    $this->buildEntityForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntityForm(array &$form, FormStateInterface $form_state) {
    $form['cluster'] = array(
      '#type' => 'value',
      '#value' => $this->entity,
    );

    $form['name'] = array(
      '#type' => 'textfield',
      '#title' => t('Administrative cluster name'),
      '#default_value' => empty($this->entity->name) ? '' : $this->entity->name,
      '#description' => t(
        'Enter the administrative cluster name that will be your Elasticsearch cluster unique identifier.'
      ),
      '#required' => TRUE,
      '#weight' => 1,
    );

    $form['cluster_id'] = array(
      '#type' => 'machine_name',
      '#title' => t('Cluster id'),
      '#default_value' => !empty($this->entity->cluster_id) ? $this->entity->cluster_id : '',
      '#maxlength' => 125,
      '#description' => t(
        'A unique machine-readable name for this Elasticsearch cluster.'
      ),
      '#machine_name' => array(
        'exists' => ['Drupal\elasticsearch_connector\Entity\Cluster', 'load'],
        'source' => array('name'),
      ),
      '#required' => TRUE,
      '#disabled' => !empty($this->entity->cluster_id),
      '#weight' => 2,
    );

    $form['url'] = array(
      '#type' => 'textfield',
      '#title' => t('Server URL'),
      '#default_value' => !empty($this->entity->url) ? $this->entity->url : '',
      '#description' => t(
        'URL and port of a server (node) in the cluster. ' .
        'Please, always enter the port even if it is default one. ' .
        'Nodes will be automatically discovered. ' .
        'Examples: http://localhost:9200 or https://localhost:443.'
      ),
      '#required' => TRUE,
      '#weight' => 3,
    );

    $form['status_info'] = $this->clusterFormInfo();

    $default = $this->clusterManager->getDefaultCluster();
    $form['default'] = array(
      '#type' => 'checkbox',
      '#title' => t('Make this cluster default connection'),
      '#description' => t(
        'If the cluster connection is not specified the API will use the default connection.'
      ),
      '#default_value' => (empty($default) || (!empty($this->entity->cluster_id) && $this->entity->cluster_id == $default)) ? '1' : '0',
      '#weight' => 4,
    );

    $form['options'] = array(
      '#tree' => TRUE,
      '#weight' => 5,
    );

    $form['options']['multiple_nodes_connection'] = array(
      '#type' => 'checkbox',
      '#title' => t('Use multiple nodes connection'),
      '#description' => t(
        'Automatically discover all nodes and use them in the cluster connection. ' .
        'Then the Elasticsearch client can distribute the query execution on random base between nodes.'
      ),
      '#default_value' => (!empty($this->entity->options['multiple_nodes_connection']) ? 1 : 0),
      '#weight' => 5.1,
    );

    $form['status'] = array(
      '#type' => 'radios',
      '#title' => t('Status'),
      '#default_value' => isset($this->entity->status) ? $this->entity->status : Cluster::ELASTICSEARCH_CONNECTOR_STATUS_ACTIVE,
      '#options' => array(
        Cluster::ELASTICSEARCH_CONNECTOR_STATUS_ACTIVE => t('Active'),
        Cluster::ELASTICSEARCH_CONNECTOR_STATUS_INACTIVE => t('Inactive'),
      ),
      '#required' => TRUE,
      '#weight' => 6,
    );

    $form['options']['use_authentication'] = array(
      '#type' => 'checkbox',
      '#title' => t('Use authentication'),
      '#description' => t(
        'Use HTTP authentication method to connect to Elasticsearch.'
      ),
      '#default_value' => (!empty($this->entity->options['use_authentication']) ? 1 : 0),
      '#suffix' => '<div id="hosting-iframe-container">&nbsp;</div>',
      '#weight' => 5.2,
    );

    $form['options']['authentication_type'] = array(
      '#type' => 'radios',
      '#title' => t('Authentication type'),
      '#description' => t('Select the http authentication type.'),
      '#options' => array(
        'Basic' => t('Basic'),
        'Digest' => t('Digest'),
        'NTLM' => t('NTLM'),
      ),
      '#default_value' => (!empty($this->entity->options['authentication_type']) ? $this->entity->options['authentication_type'] : 'Basic'),
      '#states' => array(
        'visible' => array(
          ':input[name="options[use_authentication]"]' => array('checked' => TRUE),
        ),
      ),
      '#weight' => 5.3,
    );

    $form['options']['username'] = array(
      '#type' => 'textfield',
      '#title' => t('Username'),
      '#description' => t('The username for authentication.'),
      '#default_value' => (!empty($this->entity->options['username']) ? $this->entity->options['username'] : ''),
      '#states' => array(
        'visible' => array(
          ':input[name="options[use_authentication]"]' => array('checked' => TRUE),
        ),
      ),
      '#weight' => 5.4,
    );

    $form['options']['password'] = array(
      '#type' => 'textfield',
      '#title' => t('Password'),
      '#description' => t('The password for authentication.'),
      '#default_value' => (!empty($this->entity->options['password']) ? $this->entity->options['password'] : ''),
      '#states' => array(
        'visible' => array(
          ':input[name="options[use_authentication]"]' => array('checked' => TRUE),
        ),
      ),
      '#weight' => 5.5,
    );

    $form['options']['timeout'] = array(
      '#type' => 'number',
      '#title' => t('Connection timeout'),
      '#size' => 20,
      '#required' => TRUE,
      '#description' => t(
        'After how many seconds the connection should timeout if there is no connection to Elasticsearch.'
      ),
      '#default_value' => (!empty($this->entity->options['timeout']) ? $this->entity->options['timeout'] : Cluster::ELASTICSEARCH_CONNECTOR_DEFAULT_TIMEOUT),
      '#weight' => 5.6,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $values = $form_state->getValues();

    // TODO: Check for valid URL when we are submitting the form.
    // Set default cluster.
    $default = $this->clusterManager->getDefaultCluster();
    if (empty($default) && !$values['default']) {
      $default = $this->clusterManager->setDefaultCluster($values['cluster_id']);
    }
    elseif ($values['default']) {
      $default = $this->clusterManager->setDefaultCluster($values['cluster_id']);
    }

    if ($values['default'] == 0 && !empty($default) && $default == $values['cluster_id']) {
      $this->messenger()->addWarning(
        t(
          'There must be a default connection. %name is still the default
          connection. Please change the default setting on the cluster you wish
          to set as default.',
          array(
            '%name' => $values['name'],
          )
        )
      );
    }
  }

  /**
   * Build the cluster info table for the edit page.
   *
   * @return array
   */
  protected function clusterFormInfo() {
    $element = array();

    if (isset($this->entity->url)) {
      try {
        $client_connector = $this->clientManager->getClientForCluster($this->entity);

        $cluster_info = $client_connector->getClusterInfo();
        if ($cluster_info) {
          $headers = array(
            array('data' => t('Cluster name')),
            array('data' => t('Status')),
            array('data' => t('Number of nodes')),
          );

          if (isset($cluster_info['state'])) {
            $rows = array(
              array(
                $cluster_info['health']['cluster_name'],
                $cluster_info['health']['status'],
                $cluster_info['health']['number_of_nodes'],
              ),
            );

            $element = array(
              '#theme' => 'table',
              '#header' => $headers,
              '#rows' => $rows,
              '#attributes' => array(
                'class' => array('admin-elasticsearch'),
                'id' => 'cluster-info',
              ),
            );
          }
          else {
            $rows = array(
              array(
                t('Unknown'),
                t('Unavailable'),
                '',
              ),
            );

            $element = array(
              '#theme' => 'table',
              '#header' => $headers,
              '#rows' => $rows,
              '#attributes' => array(
                'class' => array('admin-elasticsearch'),
                'id' => 'cluster-info',
              ),
            );
          }
        }
        else {
          $element['#type'] = 'markup';
          $element['#markup'] = '<div id="cluster-info">&nbsp;</div>';
        }
      }
      catch (\Exception $e) {
        $this->messenger()->addError($e->getMessage());
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    // Only save the server if the form doesn't need to be rebuilt.
    if (!$form_state->isRebuilding()) {
      try {
        parent::save($form, $form_state);
        $this->messenger()->addMessage(t('Cluster %label has been updated.', array('%label' => $this->entity->label())));
        $form_state->setRedirect('elasticsearch_connector.config_entity.list');
      }
      catch (EntityStorageException $e) {
        $form_state->setRebuild();
        watchdog_exception('elasticsearch_connector', $e);
        $this->messenger()->addError(
          $this->t('The cluster could not be saved.')
        );
      }
    }
  }

}
