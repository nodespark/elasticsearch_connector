<?php
/**
 * @file
 * Field handler for Solr fields.
 */
class elasticsearch_connector_views_handler_field extends views_handler_field {
  public function click_sort($order) {
    // TODO: Do not sort when multy value field.
    $this->query->add_sort($this->field, $order);
  }
}
