<?php

namespace Drupal\elasticsearch_connector_views\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\filter\BooleanOperator;
use Drupal\views\ViewExecutable;

/**
 * Simple filter to handle matching of boolean values
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("elasticsearch_connector_views_boolean")
 */
class ElasticsearchViewsBooleanOperator extends BooleanOperator {

}
