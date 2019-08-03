<?php

namespace Drupal\elasticsearch_connector\Plugin\search_api\data_type;

use Drupal\search_api\DataType\DataTypePluginBase;

/**
 * Provides a string data type.
 *
 * @SearchApiDataType(
 *   id = "object",
 *   label = @Translation("Object"),
 *   description = @Translation("Structured Object support"),
 *   default = "true"
 * )
 */
class ObjectDataType extends DataTypePluginBase {
}
