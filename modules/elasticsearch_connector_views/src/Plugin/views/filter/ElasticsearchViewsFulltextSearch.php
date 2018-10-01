<?php

namespace Drupal\elasticsearch_connector_views\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;

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
    $this->getFulltextFields();
    // TODO: Handle the fulltext query.
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
  }

  /**
   * Choose fulltext fields from ElasticSearch mapping if they are
   * type string and are analyzed (there is no index => not_analyzed
   * in the mapping)
   *
   * @return array fields
   */
  private function getFulltextFields() {
    //    $view_id = $this->view->storage->get('base_table');
    //    $data = Views::viewsData()->get($view_id);
    //
    //    var_dump($data);exit;
    //
    //    $index = $data['table']['base']['index'];
    //    $type = implode(',', $data['table']['base']['type']);
    //
    //    $client = $this->view->query->getElasticsearchClient();
    //
    //    $params = array(
    //      'index' => $index,
    //      'type' => $type,
    //    );
    //
    //    $mapping = $client->indices()->getMapping($params);
    //
    //    $fulltext_fields = array_keys(array_filter($mapping[$index]['mappings'][$type]['properties'], function($v) {
    //      return $v['type'] == 'string' && (!isset($v['index']) || $v['index'] != 'not_analyzed');
    //    }));
    //
    //    return array_combine($fulltext_fields, $fulltext_fields);
  }

}
