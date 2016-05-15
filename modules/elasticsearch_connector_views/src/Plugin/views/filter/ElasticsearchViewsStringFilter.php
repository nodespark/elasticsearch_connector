<?php

namespace Drupal\elasticsearch_connector_views\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\StringFilter;

/**
 * Basic textfield filter to handle string filtering commands
 * including equality, like, not like, etc.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("elasticsearch_connector_views_string")
 */
class ElasticsearchViewsStringFilter extends StringFilter {

}
