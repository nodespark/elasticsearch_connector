<?php
/**
 * @file
 * Handle the elasticsearch string fields autocomplete filter
 * using views_autocomplete_filters.
 */

class elasticsearch_connector_views_handler_filter_string_autocomplete extends views_autocomplete_filters_handler_filter_string {

  function op_equal($field) {
    if ($this->operator == '=') {
      $this->query->add_where($this->options['group'], array('term' => array($field => $this->value)));
    }
    else {
      $this->query->add_where($this->options['group'], array('not' => array(
                                                               'filter' => array(
                                                                 'term' => array($field => $this->value)))));
    }
  }

  function op_contains($field) {
    $this->query->add_where(
            $this->options['group'],
            array(
              'query' => array(
                'match' => array(
                  $field => array(
                    'query' => $this->value,
                    'type' => 'phrase'
                  ),
                )
              )
           ));
  }

  function op_word($field) {
    $where = $this->operator == 'word' ? "or" : "and";

    // Don't filter on empty strings.
    if (empty($this->value)) {
      return;
    }

    $this->query->add_where(
            $this->options['group'],
            array(
              'query' => array(
                'match' => array(
                  $field => array(
                    'query' => $this->value,
                    'type' => $where,
                  ),
                )
              )
           ));
  }

  function op_starts($field) {
    $this->query->add_where($this->options['group'], array('prefix' => array( $field => $this->value )));
  }

  function op_not_starts($field) {
    $this->query->add_where($this->options['group'], array('not' => array('prefix' => array($field => $this->value))));
  }

  function op_ends($field) {
    $this->query->add_where($this->options['group'], array('regexp' => array($field => ".*" . $this->value)));
  }

  function op_not_ends($field) {
    $this->query->add_where($this->options['group'], array('not' => array('filter' => array('regexp' => array($field => ".*" . $this->value)))));
  }

  function op_not($field) {
//    $this->query->add_where($this->options['group'], $field, '%' . db_like($this->value) . '%', 'NOT LIKE');
      $this->query->add_where(
            $this->options['group'],
            array(
              'not' => array(
                'query' => array(
                  'match' => array(
                    $field => array(
                      'query' => $this->value,
                      'operator' => 'and'
                    ),
                  )
                )
              )
           ));
  }

  function op_shorter($field) {
    $placeholder = $this->placeholder();
    $this->query->add_where_expression($this->options['group'], "LENGTH($field) < $placeholder", array($placeholder => $this->value));
  }

  function op_longer($field) {
    $placeholder = $this->placeholder();
    $this->query->add_where_expression($this->options['group'], "LENGTH($field) > $placeholder", array($placeholder => $this->value));
  }

  function op_regex($field) {
    $this->query->add_where($this->options['group'], array('regex' => array($field => $this->value)));
  }

  function op_empty($field) {
    if ($this->operator == 'empty') {
      $this->query->add_where($this->options['group'], array('missing' => array('field' => $field)));
    }
    else {
      $this->query->add_where($this->options['group'], array('exists' => array('field' => $field)));
    }

  }

  /**
   * Add this filter to the query.
   *
   * Due to the nature of fapi, the value and the operator have an unintended
   * level of indirection. You will find them in $this->operator
   * and $this->value respectively.
   */
  function query() {
    $this->ensure_my_table();
    $field = $this->real_field;

    $info = $this->operators();
    if (!empty($info[$this->operator]['method'])) {
      $this->{$info[$this->operator]['method']}($field);
    }
  }

}
