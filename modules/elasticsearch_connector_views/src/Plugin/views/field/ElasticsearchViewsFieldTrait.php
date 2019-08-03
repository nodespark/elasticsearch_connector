<?php

namespace Drupal\elasticsearch_connector_views\Plugin\views\field;

use Drupal\elasticsearch_connector_views\Plugin\views\ElasticsearchViewsHandlerTrait;
use Drupal\views\ResultRow;

/**
 * Provides a trait to use for Elasticsearch Views field handlers.
 *
 * Multi-valued field handling is taken from
 * \Drupal\views\Plugin\views\field\PrerenderList.
 */
trait ElasticsearchViewsFieldTrait {

  use ElasticsearchViewsHandlerTrait;

  /**
   * Renders a single item of a row.
   *
   * @param int $count
   *   The index of the item inside the row.
   * @param mixed $item
   *   The item for the field to render.
   *
   * @return string
   *   The rendered output.
   */
  public function render_item($count, $item) {
    return 'render item';
  }

  /**
   * Gets an array of items for the field.
   *
   * @param \Drupal\views\ResultRow $values
   *   The result row object containing the values.
   *
   * @return array
   *   An array of items for the field.
   */
  public function getItems(ResultRow $values) {
    // TODO: Implement in Elasticsearch way.
    return array();
  }

  /**
   * Render all items in this field together.
   *
   * @param array $items
   *   The items provided by getItems for a single row.
   *
   * @return string
   *   The rendered items.
   */
  public function renderItems($items) {
    return 'render items';
  }

}
