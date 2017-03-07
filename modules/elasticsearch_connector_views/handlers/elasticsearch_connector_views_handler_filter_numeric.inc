<?php

/**
 * @file
 * Definition of elasticsearch_connector_views_handler_filter_numeric.
 */

/**
 * Filter to handle numbers
 *
 * @ingroup elasticsearch_connector_views_filter_handlers
 */
class elasticsearch_connector_views_handler_filter_numeric extends views_handler_filter_numeric {
  public function query() {
    $this->ensure_my_table();
    $field = $this->real_field;

    $info = $this->operators();
    if (!empty($info[$this->operator]['method'])) {
      $this->{$info[$this->operator]['method']}($field);
    }
  }

  public function op_simple($field) {
    $value = $this->value['value'];
    switch ($this->operator) {
      case '<':
        $filter = array('range' => array($field => array('lt' => $value)));
        break;
      case '<=':
        $filter = array('range' => array($field => array('lte' => $value)));
        break;
      case '!=':
        $filter = array('not' => array('term' => array($field => $value)));
        break;
      case '>':
        $filter = array('range' => array($field => array('gt' => $value)));
        break;
      case '>=':
        $filter = array('range' => array($field => array('gte' => $value)));
        break;
      case '=':
      default:
        $filter = array('term' => array($field => $value));
        break;
    }

    $this->query->add_where($this->options['group'], $filter);

  }

  public function op_between($field) {
    $min = $this->value['min'];
    $max = $this->value['max'];

    $filter = array('range' => array($field => array('gte' => $min, 'lte' => $max)));

    if ($this->operator != 'between') {
      $filter = array('not' => $filter);
    }

    $this->query->add_where($this->options['group'], $filter);
  }
}