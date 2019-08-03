<?php

namespace Drupal\Tests\elasticsearch_connector\Unit\ElasticSearch\Parameters\Factory;

use Drupal\search_api\Query\Condition;
use Drupal\Tests\UnitTestCase;
use Drupal\elasticsearch_connector\ElasticSearch\Parameters\Factory\FilterFactory;

/**
 * This test is long because it tests a long method. It just repeats
 * the same pattern over and over where a condition is mocked to
 * test each of the ramifications.
 *
 * @coversDefaultClass \Drupal\elasticsearch_connector\ElasticSearch\Parameters\Factory\FilterFactory
 *
 * @group elasticsearch_connector
 */
class FilterFactoryTest extends UnitTestCase {

  /**
   * @covers ::filterFromCondition
   */
  public function testFilterFromConditionA() {
    // Empty and not empty operators.
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

  /**
   * @covers ::filterFromCondition
   */
  public function testFilterFromConditionB() {
    // Normal filters
    /** @var \Prophecy\Prophecy\ObjectProphecy $condition */
    $condition = $this->prophesize(Condition::class);

    $condition->getValue()
      ->willReturn('bar');

    $condition->getOperator()
      ->willReturn('=');

    $condition->getField()
      ->willReturn('foo');

    $filter = FilterFactory::filterFromCondition($condition->reveal());
    $expected_filter = [
      'term' => [
        'foo' => 'bar'
      ],
    ];
    $this->assertEquals($expected_filter, $filter);

    /** @var \Prophecy\Prophecy\ObjectProphecy $condition */
    $condition = $this->prophesize(Condition::class);

    $condition->getValue()
      ->willReturn(['bar', 'baz']);

    $condition->getOperator()
      ->willReturn('IN');

    $condition->getField()
      ->willReturn('foo');

    $filter = FilterFactory::filterFromCondition($condition->reveal());
    $expected_filter = [
      'terms' => [
        'foo' => ['bar', 'baz'],
      ],
    ];
    $this->assertEquals($expected_filter, $filter);

    /** @var \Prophecy\Prophecy\ObjectProphecy $condition */
    $condition = $this->prophesize(Condition::class);

    $condition->getValue()
      ->willReturn('bar');

    $condition->getOperator()
      ->willReturn('<>');

    $condition->getField()
      ->willReturn('foo');

    $filter = FilterFactory::filterFromCondition($condition->reveal());
    $expected_filter = [
      'not' => [
        'filter' =>[
          'term' => ['foo' => 'bar'],
        ],
      ],
    ];
    $this->assertEquals($expected_filter, $filter);

    /** @var \Prophecy\Prophecy\ObjectProphecy $condition */
    $condition = $this->prophesize(Condition::class);

    $condition->getValue()
      ->willReturn(1);

    $condition->getOperator()
      ->willReturn('>');

    $condition->getField()
      ->willReturn('foo');

    $filter = FilterFactory::filterFromCondition($condition->reveal());
    $expected_filter = [
      'range' => [
        'foo' => [
          'from' => 1,
          'to' => NULL,
          'include_lower' => FALSE,
          'include_upper' => FALSE,
        ],
      ],
    ];
    $this->assertEquals($expected_filter, $filter);

    /** @var \Prophecy\Prophecy\ObjectProphecy $condition */
    $condition = $this->prophesize(Condition::class);

    $condition->getValue()
      ->willReturn(1);

    $condition->getOperator()
      ->willReturn('>=');

    $condition->getField()
      ->willReturn('foo');

    $filter = FilterFactory::filterFromCondition($condition->reveal());
    $expected_filter = [
      'range' => [
        'foo' => [
          'from' => 1,
          'to' => NULL,
          'include_lower' => TRUE,
          'include_upper' => FALSE,
        ],
      ],
    ];
    $this->assertEquals($expected_filter, $filter);

    /** @var \Prophecy\Prophecy\ObjectProphecy $condition */
    $condition = $this->prophesize(Condition::class);

    $condition->getValue()
      ->willReturn(1);

    $condition->getOperator()
      ->willReturn('<');

    $condition->getField()
      ->willReturn('foo');

    $filter = FilterFactory::filterFromCondition($condition->reveal());
    $expected_filter = [
      'range' => [
        'foo' => [
          'from' => NULL,
          'to' => 1,
          'include_lower' => FALSE,
          'include_upper' => FALSE,
        ],
      ],
    ];
    $this->assertEquals($expected_filter, $filter);

    /** @var \Prophecy\Prophecy\ObjectProphecy $condition */
    $condition = $this->prophesize(Condition::class);

    $condition->getValue()
      ->willReturn(1);

    $condition->getOperator()
      ->willReturn('<=');

    $condition->getField()
      ->willReturn('foo');

    $filter = FilterFactory::filterFromCondition($condition->reveal());
    $expected_filter = [
      'range' => [
        'foo' => [
          'from' => NULL,
          'to' => 1,
          'include_lower' => FALSE,
          'include_upper' => TRUE,
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
      ->willReturn('other-operator');

    $condition->getField()
      ->willReturn('foo');

    $this->setExpectedException(\Exception::class, 'Incorrect filter criteria');
    FilterFactory::filterFromCondition($condition->reveal());
  }

}
