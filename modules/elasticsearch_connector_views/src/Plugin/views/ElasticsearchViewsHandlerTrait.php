<?php

namespace Drupal\elasticsearch_connector_views\Plugin\views;

/**
 * Provides a trait to use for Search API Views handlers.
 */
trait ElasticsearchViewsHandlerTrait {

  /**
   * Overrides the Views handlers' ensureMyTable() method.
   *
   * This is done since adding a table to a Search API query is neither
   * necessary nor possible, but we still want to stay as compatible as possible
   * to the default SQL query plugin.
   */
  public function ensureMyTable() {
  }

  /**
   * Determines the entity type used by this handler.
   *
   * If this handler uses a relationship, the base class of the relationship is
   * taken into account.
   *
   * @return string
   *   The machine name of the entity type.
   *
   * @see \Drupal\views\Plugin\views\HandlerBase::getEntityType()
   */
  public function getEntityType() {
    if (isset($this->definition['entity_type'])) {
      return $this->definition['entity_type'];
    }
    return parent::getEntityType();
  }

  /**
   * Returns the active search index.
   *
   * @return string
   *   The index to use with this filter, or NULL if none could be
   *   loaded.
   */
  protected function getIndex() {
    // TODO: Implement.
    return NULL;
  }

  /**
   * Retrieves the query plugin.
   */
  public function getQuery() {
    return NULL;
  }

}
