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
    if (is_null($condition->getValue())) {
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
            'Value is empty for ' . $condition->getField() . '. Incorrect filter criteria is using for searching!'
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
            'bool' => [
              'must_not' => [
                'term' => [$condition->getField() => $condition->getValue()],
              ],
            ]
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

        case 'BETWEEN':
          $filter = [
            'range' => [
              $condition->getField() => [
                'from' => (!empty($condition->getValue()[0])) ? $condition->getValue()[0] : NULL,
                'to' => (!empty($condition->getValue()[1])) ? $condition->getValue()[1] : NULL,
                'include_lower' => FALSE,
                'include_upper' => FALSE,
              ],
            ],
          ];
          break;

        case 'NOT BETWEEN':
          $filter = [
            'bool' => [
              'must_not' => [
                'range' => [
                  $condition->getField() => [
                    'from' => (!empty($condition->getValue()[0])) ? $condition->getValue()[0] : NULL,
                    'to' => (!empty($condition->getValue()[1])) ? $condition->getValue()[1] : NULL,
                    'include_lower' => FALSE,
                    'include_upper' => FALSE,
                  ],
                ],
              ]
            ]
          ];
          break;

        default:
          throw new \Exception('Undefined operator ' . $condition->getOperator() . ' for ' . $condition->getField() . ' field! Incorrect filter criteria is using for searching!');
      }
    }

    return $filter;
  }

}
