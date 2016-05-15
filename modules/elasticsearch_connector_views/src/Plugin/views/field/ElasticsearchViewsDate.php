<?php

namespace Drupal\elasticsearch_connector_views\Plugin\views\field;

use Drupal\views\Plugin\views\field\Date;
use Drupal\views\Plugin\views\field\MultiItemsFieldHandlerInterface;
use Drupal\views\ResultRow;

/**
 * Handles the display of date fields in Search API Views.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("elasticsearch_connector_views_date")
 */
class ElasticsearchViewsDate extends Date {
  // TODO: Implement the MultiItemsFieldHandlerInterface interface.
}
