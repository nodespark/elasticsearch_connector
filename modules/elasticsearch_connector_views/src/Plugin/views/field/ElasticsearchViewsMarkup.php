<?php

namespace Drupal\elasticsearch_connector_views\Plugin\views\field;

use Drupal\views\Plugin\views\field\Markup;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ViewExecutable;

/**
 * Provides a default handler for fields in Search API Views.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("elasticsearch_connector_views_markup")
 */
class ElasticsearchViewsMarkup extends Markup {

  /**
  * {@inheritdoc}
  */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    if (!isset($this->definition['format'])) {
      $this->definition['format'] = filter_default_format();
    }
    parent::init($view, $display, $options);
  }

}
