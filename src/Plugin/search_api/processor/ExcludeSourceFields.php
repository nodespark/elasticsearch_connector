<?php
/**
 * @file
 */

namespace Drupal\elasticsearch_connector\Plugin\search_api\processor;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\elasticsearch_connector\Plugin\search_api\backend\SearchApiElasticsearchBackend;
use Drupal\search_api\Annotation\SearchApiProcessor;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Plugin\PluginFormTrait;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Query\QueryInterface;

/**
 * @SearchApiProcessor(
 *   id = "exclude_source_fields",
 *   label = @Translation("Exclude source fields"),
 *   description = @Translation("Exclude certain source fields from search results"),
 *   stages = {
 *     "preprocess_query" = -20
 *   }
 * )
 */
class ExcludeSourceFields extends ProcessorPluginBase implements PluginFormInterface {

  use PluginFormTrait;

  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state
  ) {
    $form['#description'] = $this->t(
      'Select the fields to exclude from the source field in search results. See <a href=":url">the Elasticsearch documentation on source filtering</a> for more info.',
      [
        ':url' => 'https://www.elastic.co/guide/en/elasticsearch/reference/current/search-request-source-filtering.html',
      ]
    );

    foreach ($this->index->getFields() as $field) {
      $excluded = !empty($this->configuration['fields'][$field->getFieldIdentifier()]);
      $form['fields'][$field->getFieldIdentifier()] = array(
        '#type' => 'checkbox',
        '#title' => $field->getPrefixedLabel(),
        '#default_value' => $excluded,
      );
    }

    return $form;
  }

  public function preprocessSearchQuery(QueryInterface $query) {
    $excluded_fields = array_filter($this->configuration['fields']);

    $query->setOption(
      'elasticsearch_connector_exclude_source_fields',
      array_keys($excluded_fields)
    );
  }

  public static function supportsIndex(IndexInterface $index) {
    $backend = $index->getServerInstance()->getBackend();
    return $backend instanceof SearchApiElasticsearchBackend;
  }
}