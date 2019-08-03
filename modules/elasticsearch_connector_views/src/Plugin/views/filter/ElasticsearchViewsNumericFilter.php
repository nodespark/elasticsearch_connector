<?php

namespace Drupal\elasticsearch_connector_views\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\NumericFilter;

/**
 * Simple filter to handle greater than/less than filters.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("elasticsearch_connector_views_numeric")
 */
class ElasticsearchViewsNumericFilter extends NumericFilter {

}
