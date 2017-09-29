<?php

namespace Drupal\Tests\elasticsearch_connector\Unit\ElasticSearch\Parameters\Factory;

use Drupal\search_api\Query\Condition;
use Drupal\Tests\UnitTestCase;
use Drupal\elasticsearch_connector\ElasticSearch\Parameters\Factory\FilterFactory;

/**
 * @coversDefaultClass \Drupal\elasticsearch_connector\ElasticSearch\Parameters\Factory\FilterFactory
 *
 * @group elasticsearch_connector
 */
class FilterFactoryTest extends UnitTestCase {

  /**
   * @covers ::filterFromCondition
   */
  public function testFilterFromCondition() {

    // Test the <> operator.
    /** @var \Prophecy\Prophecy\ObjectProphecy $condition */
    $condition = $this->prophesize(Condition::class);

    $condition->getValue()
      ->willReturn(FALSE);

    $condition->getOperator()
      ->willReturn('<>');

    $condition->getField()
      ->willReturn('foo');

    $filter = FilterFactory::filterFromCondition($condition->reveal());
    $expected_filter = [
      'exists' =>
        [
          'field' => 'foo',
        ],
    ];
    $this->assertEquals($expected_filter, $filter);

    // Thest the = operator.
    /** @var \Prophecy\Prophecy\ObjectProphecy $condition */
    $condition = $this->prophesize(Condition::class);

    $condition->getValue()
      ->willReturn(FALSE);

    $condition->getOperator()
      ->willReturn('=');

    $condition->getField()
      ->willReturn('foo');

    $filter = FilterFactory::filterFromCondition($condition->reveal());
    $expected_filter = [
      'bool' => [
        'must_not' => [
          'exists' => ['field' => 'foo'],
        ],
      ],
    ];
    $this->assertEquals($expected_filter, $filter);

    // Other operators will throw an exception.
    /** @var \Prophecy\Prophecy\ObjectProphecy $condition */
    $condition = $this->prophesize(Condition::class);

    $condition->getValue()
      ->willReturn(FALSE);

    $condition->getOperator()
      ->willReturn('>');

    $condition->getField()
      ->willReturn('foo');

    $this->setExpectedException(\Exception::class, 'Incorrect filter criteria');
    FilterFactory::filterFromCondition($condition->reveal());
  }

}
