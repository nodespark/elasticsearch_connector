<?php

namespace Drupal\elasticsearch_connector_views\Plugin\views\join;

use Drupal\views\Plugin\views\join\JoinPluginBase;

/**
 * Represents a join in the Search API Views tables.
 *
 * Since the concept of joins does not exist in the Elasticsearch, this handler
 * does nothing except override the default behavior.
 *
 * @ingroup views_join_handlers
 *
 * @ViewsJoin("elasticsearch_connector_views")
 */
class ElasticsearchViewsJoin extends JoinPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildJoin($select_query, $table, $view_query) {
  }

}
