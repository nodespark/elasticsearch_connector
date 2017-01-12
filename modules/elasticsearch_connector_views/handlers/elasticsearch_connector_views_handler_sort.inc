<?php
/**
 * @file
 * Class for sorting for a field.
 */
class elasticsearch_connector_views_handler_sort extends views_handler_sort {
  function query() {
    $this->query->add_sort($this->real_field, drupal_strtolower($this->options['order']));
  }
}
