<?php
/**
 * @file
 * Contains the SearchApiHighlight class.
 */

class ElasticsearchConnectorSearchApiHighlight extends SearchApiAbstractProcessor {

  /**
   * {@inheritdoc}
   */
  public function __construct(SearchApiIndex $index, array $options = array()) {
    parent::__construct($index, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function supportsIndex(SearchApiIndex $index) {
    $server = $index->server();
    if (!empty($server) && $server->class == 'search_api_elasticsearch_connector') {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function configurationForm() {
    $this->options += array(
      'prefix' => '<strong>',
      'suffix' => '</strong>',
      'excerpt' => TRUE,
      'excerpt_length' => 256,
      'excerpt_fragments' => 3,
      'concatenator' => '...',
      'highlight' => 'always',
    );

    $form['prefix'] = array(
      '#type' => 'textfield',
      '#title' => t('Highlighting prefix'),
      '#description' => t('Text/HTML that will be prepended to all occurrences of search keywords in highlighted text.'),
      '#default_value' => $this->options['prefix'],
    );
    $form['suffix'] = array(
      '#type' => 'textfield',
      '#title' => t('Highlighting suffix'),
      '#description' => t('Text/HTML that will be appended to all occurrences of search keywords in highlighted text.'),
      '#default_value' => $this->options['suffix'],
    );
    $form['excerpt'] = array(
      '#type' => 'checkbox',
      '#title' => t('Create excerpt'),
      '#description' => t('When enabled, an excerpt will be created for searches with keywords, containing all occurrences of keywords in a fulltext field.'),
      '#default_value' => $this->options['excerpt'],
    );

    $form['excerpt_length'] = array(
      '#type' => 'textfield',
      '#title' => t('Excerpt length'),
      '#description' => t('The requested length of the excerpt, in characters. This is equivalent of Elasticsearch\'s fragment_size option.'),
      '#default_value' => $this->options['excerpt_length'],
      '#element_validate' => array('element_validate_integer_positive'),
      '#states' => array(
        'visible' => array(
          '#edit-processors-search-api-highlighting-settings-excerpt' => array(
            'checked' => TRUE,
          ),
        ),
      ),
    );

    $form['excerpt_fragments'] = array(
      '#type' => 'textfield',
      '#title' => t('Excerpt fragments'),
      '#description' => t('The number of highlighted excerpt fragments.'),
      '#default_value' => $this->options['excerpt_fragments'],
      '#element_validate' => array('element_validate_integer_positive'),
      '#states' => array(
        'visible' => array(
          '#edit-processors-search-api-highlighting-settings-excerpt' => array(
            'checked' => TRUE,
          ),
        ),
      ),
    );

    $form['concatenator'] = array(
      '#type' => 'textfield',
      '#title' => t('Concatenator'),
      '#description' => t('The concatenator between different excerpt fragments used for both the excerpt and fields highlighting.'),
      '#default_value' => $this->options['concatenator'],
    );

    $form['highlight'] = array(
      '#type' => 'select',
      '#title' => t('Highlight returned field data'),
      '#description' => t('Select whether returned fields should be highlighted.'),
      '#options' => array(
        'always' => t('Always'),
        'server' => t('If the server returns fields'),
        'never' => t('Never'),
      ),
      '#default_value' => $this->options['highlight'],
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function configurationFormValidate(array $form, array &$values, array &$form_state) {
    // Overridden so $form['fields'] is not checked.
  }

  /**
   * {@inheritdoc}
   */
  public function postprocessSearchResults(array &$response, SearchApiQuery $query) {
    if (!$this->supportsIndex($query->getIndex())) {
      throw new SearchApiException("This processor is only available for Elasticsearch connector search api implementation.");
    }

    if (!empty($response['results'])) {
      foreach ($response['results'] as $id => &$result) {
        $result = $this->parseHighlightFields($result, $query);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessSearchQuery(SearchApiQuery $query) {
    if (!$this->supportsIndex($query->getIndex())) {
      throw new SearchApiException("This processor is only available for Elasticsearch connector search api implementation.");
    }

    $fields = $query->getFields();
    if (!empty($fields)) {
      $params['highlight']['pre_tags'] = array($this->options['prefix']);
      $params['highlight']['post_tags'] = array($this->options['suffix']);
      $params['highlight']['fragment_size'] = $this->options['excerpt_length'];
      $params['highlight']['number_of_fragments'] = $this->options['excerpt_fragments'];

      foreach ($fields as $field) {
        $params['highlight']['fields'][$field] = new stdClass();
      }

      $elasticsearch_processors_params = $query->getOption('elasticsearch_processors_params', array());
      $elasticsearch_processors_params['highlight'] = $params['highlight'];
      $query->setOption('elasticsearch_processors_params', $elasticsearch_processors_params);
    }
  }

  /**
   * Transform the response
   * @param array $result
   * @param SearchApiQueryInterface $query
   * @return array $result
   */
  protected function parseHighlightFields($result, SearchApiQueryInterface $query) {
    $fields = $query->getFields();
    $result_fields = &$result['fields'];
    if (!empty($result['highlight']) && !empty($fields)) {
      foreach ($fields as $field) {
        if (isset($result_fields[$field]) && !empty($result['highlight'][$field])) {
          if ($this->options['highlight'] != 'never') {
            $result_fields[$field] = implode($this->options['concatenator'], $result['highlight'][$field]);
          }

          if ($this->options['excerpt']) {
            if (!isset($result['excerpt'])) {
              $result['excerpt'] = implode($this->options['concatenator'], $result['highlight'][$field]);
            }
            else {
              $result['excerpt'] .= $this->options['concatenator'] . implode($this->options['concatenator'], $result['highlight'][$field]);
            }
          }
        }
      }
    }

    return $result;
  }

}