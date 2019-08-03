<?php

namespace Drupal\elasticsearch_connector_views\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Default implementation of the base filter plugin.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("elasticsearch_connector_views_fulltext_filter")
 */
class ElasticsearchViewsFulltextSearch extends FilterPluginBase {



  /**
   * {@inheritdoc}
   */
  public function query() {
    $fields = $this->options['fields'];
    if (!empty($this->value[0])) {
      foreach ($fields as $field) {
        $this->query->where['conditions'][$field] = $this->value[0];
      }
    }
  }



  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $fields = $this->getFulltextFields();
    if (!empty($fields)) {
      $form['fields'] = array(
        '#type' => 'select',
        '#title' => t('Searched fields'),
        '#description' => t('Select the fields that will be searched.'),
        '#options' => $fields,
        '#size' => min(4, count($fields)),
        '#multiple' => TRUE,
        '#default_value' => $this->options['fields'],
        '#required' => TRUE,
      );
    }
    else {
      $form['fields'] = array(
        '#type' => 'value',
        '#value' => array(),
      );
    }

    if (isset($form['expose'])) {
      $form['expose']['#weight'] = -5;
    }

  }

  /**
   * Choose fulltext fields from ElasticSearch mapping if they are
   * type string and are analyzed (there is no index => not_analyzed
   * in the mapping)
   *
   * @return array fields
   */
  private function getFulltextFields() {

    $view_id = $this->view->storage->get('base_table');
    $data = Views::viewsData()->get($view_id);

    $index = $data['table']['base']['index'];
    $cluster_id = $data['table']['base']['cluster_id'];

    /** @var \Drupal\elasticsearch_connector\Entity\Cluster $elasticsearchCluster */
    $elasticsearchCluster = \Drupal::service('entity.manager')->getStorage('elasticsearch_cluster')->load($cluster_id);
    /** @var \Drupal\elasticsearch_connector\ElasticSearch\ClientManagerInterface $clientManager */
    $clientManager = \Drupal::service('elasticsearch_connector.client_manager');
    $client = $clientManager->getClientForCluster($elasticsearchCluster);

    $params = array(
      'index' => $index,
    );
    $mapping = $client->indices()->getMapping($params);

    $fulltext_fields = array_keys(array_filter($mapping[$index]['mappings']['properties'], function($v) {
      return $v['type'] == 'text' && (!isset($v['index']) || $v['index'] != 'not_analyzed');
    }));

    return array_combine($fulltext_fields, $fulltext_fields);
  }


  /**
   * Provide a simple textfield for equality
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    // We have to make some choices when creating this as an exposed
    // filter form. For example, if the operator is locked and thus
    // not rendered, we can't render dependencies; instead we only
    // render the form items we need.
    $which = 'all';
    if (!empty($form['operator'])) {
      $source = ':input[name="options[operator]"]';
    }
    if ($exposed = $form_state->get('exposed')) {
      $identifier = $this->options['expose']['identifier'];

      if (empty($this->options['expose']['use_operator']) || empty($this->options['expose']['operator_id'])) {
        // Exposed and locked.
        $which = in_array($this->operator, $this->operatorValues(1)) ? 'value' : 'none';
      }
      else {
        $source = ':input[name="' . $this->options['expose']['operator_id'] . '"]';
      }
    }

    if ($which == 'all' || $which == 'value') {
      $form['value'] = array(
        '#type' => 'textfield',
        '#title' => $this->t('Value'),
        '#size' => 30,
        '#default_value' => $this->value,
      );
      $user_input = $form_state->getUserInput();
      if ($exposed && !isset($user_input[$identifier])) {
        $user_input[$identifier] = $this->value;
        $form_state->setUserInput($user_input);
      }

      if ($which == 'all') {
        // Setup #states for all operators with one value.
        foreach ($this->operatorValues(1) as $operator) {
          $form['value']['#states']['visible'][] = array(
            $source => array('value' => $operator),
          );
        }
      }
    }

    if (!isset($form['value'])) {
      // Ensure there is something in the 'value'.
      $form['value'] = array(
        '#type' => 'value',
        '#value' => NULL,
      );
    }
  }

  /**
   * Helper function to define Options.
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['expose']['contains']['required'] = array('default' => FALSE);

    $options['min_length']['default'] = '';
    $options['fields']['default'] = [];

    return $options;
  }

  /**
   * Helper function to build Admin Summary.
   */
  public function adminSummary() {
    if ($this->isAGroup()) {
      return $this->t('grouped');
    }
    if (!empty($this->options['exposed'])) {
      return $this->t('exposed');
    }

    $options = $this->operatorOptions('short');
    $output = '';
    if (!empty($options[$this->operator])) {
      $output = $options[$this->operator];
    }
    if (in_array($this->operator, $this->operatorValues(1))) {
      $output .= ' ' . $this->value;
    }
    return $output;
  }

  /**
   * Helper function to build operator values.
   */
  protected function operatorValues($values = 1) {
    $options = array();
    foreach ($this->operators() as $id => $info) {
      if (isset($info['values']) && $info['values'] == $values) {
        $options[] = $id;
      }
    }

    return $options;
  }

  /**
   * Build strings from the operators() for 'select' options
   */
  public function operatorOptions($which = 'title') {
    $options = array();
    foreach ($this->operators() as $id => $info) {
      $options[$id] = $info[$which];
    }

    return $options;
  }

  /**
   * Helper function to define opertators.
   */
  public function operators() {
    $operators = array(
      'word' => array(
        'title' => $this->t('Contains any word'),
        'short' => $this->t('has word'),
        'method' => 'opContainsWord',
        'values' => 1,
      ),
    );
    return $operators;
  }



}
