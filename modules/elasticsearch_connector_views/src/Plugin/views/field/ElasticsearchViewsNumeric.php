<?php

namespace Drupal\elasticsearch_connector_views\Plugin\views\field;

use Drupal\Core\StringTranslation\PluralTranslatableMarkup;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\field\MultiItemsFieldHandlerInterface;
use Drupal\views\Plugin\views\field\NumericField;
use Drupal\views\ViewExecutable;

/**
 * Displays numeric data.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("elasticsearch_connector_views_numeric")
 */
class ElasticsearchViewsNumeric extends NumericField {

}
