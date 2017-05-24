<?php

namespace Drupal\elasticsearch_connector\ElasticSearch\Parameters\Factory;

use Drupal\search_api\Query\Condition;

/**
 * Class FilterFactory.
 */
class FilterFactory {

  /**
   * Get query by Condition instance.
   *
   * @param Condition $condition
   *
   * @return array
   *
   * @throws \Exception
   */
  public static function filterFromCondition(Condition $condition) {
    // Handles "empty", "not empty" operators.
    if (!$condition->getValue()) {
      switch ($condition->getOperator()) {
        case '<>':
          $filter = [
            'exists' => ['field' => $condition->getField()],
          ];
          break;

        case '=':
          $filter = [
            'bool' => [
              'must_not' => [
                'exists' => ['field' => $condition->getField()],
              ],
            ],
          ];
          break;

        default:
          throw new \Exception(
            t('Value is empty for :field_id! Incorrect filter criteria is using for searching!', [':field_id' => $condition->getField()])
          );
      }
    }
    // Normal filters.
    else {
      switch ($condition->getOperator()) {
        case '=':
          $filter = [
            'term' => [$condition->getField() => $condition->getValue()],
          ];
          break;

        case 'IN':
          $filter = [
            'terms' => [$condition->getField() => array_values($condition->getValue())],
          ];
          break;

        case '<>':
          $filter = [
            'not' => [
              'filter' => [
                'term' => [$condition->getField() => $condition->getValue()],
              ],
            ],
          ];
          break;

        case '>':
          $filter = [
            'range' => [
              $condition->getField() => [
                'from' => $condition->getValue(),
                'to' => NULL,
                'include_lower' => FALSE,
                'include_upper' => FALSE,
              ],
            ],
          ];
          break;

        case '>=':
          $filter = [
            'range' => [
              $condition->getField() => [
                'from' => $condition->getValue(),
                'to' => NULL,
                'include_lower' => TRUE,
                'include_upper' => FALSE,
              ],
            ],
          ];
          break;

        case '<':
          $filter = [
            'range' => [
              $condition->getField() => [
                'from' => NULL,
                'to' => $condition->getValue(),
                'include_lower' => FALSE,
                'include_upper' => FALSE,
              ],
            ],
          ];
          break;

        case '<=':
          $filter = [
            'range' => [
              $condition->getField() => [
                'from' => NULL,
                'to' => $condition->getValue(),
                'include_lower' => FALSE,
                'include_upper' => TRUE,
              ],
            ],
          ];
          break;

        default:
          throw new \Exception(
            t(
              'Undefined operator :field_operator for :field_id field! Incorrect filter criteria is using for searching!',
              [
                ':field_operator' => $condition->getOperator(),
                ':field_id' => $condition->getField(),
              ]
            )
          );
      }
    }

    return $filter;
  }

}
