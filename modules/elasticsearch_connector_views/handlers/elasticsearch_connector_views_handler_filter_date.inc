<?php
/**
 * @file
 *
 * Specific handler for date field for elasticsearch.
 *
 */
class elasticsearch_connector_views_handler_filter_date extends views_handler_filter_date {
  public function query() {
    $this->ensure_my_table();
    $field = $this->real_field;

    $info = $this->operators();
    if (!empty($info[$this->operator]['method'])) {
      $this->{$info[$this->operator]['method']}($field);
    }
  }

  public function op_simple($field) {
    $value = date_iso8601(strtotime($this->value['value']));
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
    $min = date_iso8601(strtotime($this->value['min']));
    $max = date_iso8601(strtotime($this->value['max']));

    $filter = array('range' => array($field => array('gte' => $min, 'lte' => $max)));

    if ($this->operator != 'between') {
      $filter = array('not' => $filter);
    }

    $this->query->add_where($this->options['group'], $filter);
  }

}