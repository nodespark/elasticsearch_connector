<?php
/**
 * @file
 *
 * Implementing filter_handler for elasticsearch.
 *
 */
class elasticsearch_connector_views_handler_filter extends views_handler_filter {

  /**
   * Provide a simple textfield for equality
   */
  function value_form(&$form, &$form_state) {
    $form['value'] = array(
      '#type' => 'textfield',
      '#title' => filter_xss(t($this->definition['title'])),
      '#default_value' => $this->value,
    );
  }

}