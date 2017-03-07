<?php
/**
 * @file
 * Contains the SearchApiSpellcheckSolr class.
 */

/**
 * Spellcheck class which can provide spelling suggestions. The constructor
 * populates the instance with any suggestions returned by Solr.
 */
class SearchApiSpellcheckElasticsearch extends SearchApiSpellcheck {

  /**
   * Constructs a SearchApiSpellcheckSolr object.
   *
   * If Solr has returned spelling suggestion then loop through them and add
   * them to this spellcheck service.
   *
   * @param object $response
   *   The Solr response object.
   */
  function __construct($response) {
    if (isset($response['suggest'])) {
      $suggestions = $response['suggest'];
      foreach ($suggestions as $suggestion_name => $data) {
        foreach ($data as $suggestion) {
          foreach ($suggestion['options'] as $option) {
            $this->addSuggestion(new SearchApiSpellcheckSuggestion($suggestion['text'], $option['text']));
          }
        }
      }
    }
  }

}
