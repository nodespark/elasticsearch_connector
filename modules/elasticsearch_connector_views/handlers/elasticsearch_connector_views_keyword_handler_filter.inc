<?php
/**
 * @file
 * Fulltext keyword search.
 */
class elasticsearch_connector_views_keyword_handler_filter extends elasticsearch_connector_views_handler_filter {
  function query() {
    if (!empty($this->value) && !empty($this->value[0])) {
      $this->query->add_parameter('q', $this->value[0]);
    }

    if (!empty($this->options['fields'])) {
      $this->query->add_parameter('fulltext_fields', $this->options['fields']);
    }

    if (!empty($this->operator)) {
      $this->query->add_parameter('fulltext_operator', $this->operator);
    }

  }

  public function options_form(&$form, &$form_state) {
    parent::options_form($form, $form_state);

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
   * Provide a list of options for the operator form.
   */
  public function operator_options() {
    return array(
      'AND' => t('Contains all of these words'),
      'OR'  => t('Contains any of these words'),
    );
  }

  /**
   * Specify the options this filter uses.
   */
  public function option_definition() {
    $options = parent::option_definition();

    $options['operator']['default'] = 'AND';

    $options['fields'] = array('default' => array());

    return $options;
  }

  /**
   * Choose fulltext fields from ElasticSearch mapping if they are
   * type string and are analyzed (there is no index => not_analyzed
   * in the mapping)
   *
   * @return array fields
   */
  private function getFulltextFields() {
    $base_table_parts = explode("__", $this->table);

    $cluster_id = $base_table_parts[1];
    $index = $base_table_parts[2];
    $type = $base_table_parts[3];

    $client = elasticsearch_connector_get_client_by_id($cluster_id);

    $params = array(
      'index' => $index,
      'type' => $type,
    );

    $mapping = $client->indices()->getMapping($params);

    $fulltext_fields = array_keys(array_filter($mapping[$index]['mappings'][$type]['properties'], function($v) {
      return $v['type'] == 'string' && (!isset($v['index']) || $v['index'] != 'not_analyzed');
    }));

    return array_combine($fulltext_fields, $fulltext_fields);
  }

}
