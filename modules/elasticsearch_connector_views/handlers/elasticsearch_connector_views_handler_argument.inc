<?php
/**
 * @file
 * Class that allows searching the site with Elasticsearch through a view.
 */
class elasticsearch_connector_views_handler_argument extends views_handler_argument {
  function query($group_by = NULL) {
    $this->query->add_where(0, array('term' => array($this->real_field => $this->argument)));
  }
}
