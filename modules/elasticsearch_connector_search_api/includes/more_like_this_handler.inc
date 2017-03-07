<?php

/**
 * @file
 * Contains SearchApiViewsHandlerArgumentMoreLikeThis.
 */

/**
 * Views argument handler providing a list of related items for search servers
 * supporting the "search_api_mlt" feature.
 */
class ElasticsearchConnectorSearchApiViewsHandlerArgumentMoreLikeThis extends SearchApiViewsHandlerArgumentMoreLikeThis {

  /**
   * Specify the options this filter uses.
   */
  public function option_definition() {
    $options = parent::option_definition();
    $options['max_query_terms'] = array('default' => 1);
    $options['min_doc_freq'] = array('default' => 1);
    $options['min_term_freq'] = array('default' => 1);
    return $options;
  }

  /**
   * Extend the options form a bit.
   */
  public function options_form(&$form, &$form_state) {
    parent::options_form($form, $form_state);
    $index = search_api_index_load(substr($this->table, 17));
    if ($index->server()->class == 'search_api_elasticsearch_connector') {
      $form['max_query_terms'] = array(
        '#type' => 'textfield',
        '#title' => t('Max query terms'),
        '#default_value' => $this->options['max_query_terms'],
        '#element_validate' => array('element_validate_integer_positive'),
        '#description' => t('The maximum number of query terms that will be included in any generated query.'),
      );

      $form['min_doc_freq'] = array(
        '#type' => 'textfield',
        '#title' => t('Min doc frequency'),
        '#default_value' => $this->options['min_doc_freq'],
        '#element_validate' => array('element_validate_integer_positive'),
        '#description' => t('The frequency at which words will be ignored which do not occur in at least this many docs.'),
      );

      $form['min_term_freq'] = array(
        '#type' => 'textfield',
        '#title' => t('Min term frequency'),
        '#default_value' => $this->options['min_term_freq'],
        '#element_validate' => array('element_validate_integer_positive'),
        '#description' => t('The frequency below which terms will be ignored in the source doc.'),
      );
    }
  }

  /**
   * Set up the query for this argument.
   *
   * The argument sent may be found at $this->argument.
   */
  public function query($group_by = FALSE) {
    $server = $this->query->getIndex()->server();
    if (!$server->supportsFeature('search_api_mlt')) {
      $class = search_api_get_service_info($server->class);
      watchdog('search_api_views', 'The search service "@class" does not offer "More like this" functionality.',
          array('@class' => $class['name']), WATCHDOG_ERROR);
      $this->query->abort();
      return;
    }
    $fields = $this->options['fields'] ? $this->options['fields'] : array();
    if (empty($fields)) {
      foreach ($this->query->getIndex()->options['fields'] as $key => $field) {
        $fields[] = $key;
      }
    }
    $mlt = array(
      'id'                => $this->argument,
      'fields'            => $fields,
      'max_query_terms'   => $this->options['max_query_terms'],
      'min_doc_freq'      => $this->options['min_doc_freq'],
      'min_term_freq'     => $this->options['min_term_freq']
    );
    $this->query->getSearchApiQuery()->setOption('search_api_mlt', $mlt);
  }
}
