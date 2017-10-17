<?php

namespace Drupal\Tests\elasticsearch_connector\Unit\ElasticSearch\Parameters\Factory;

use Drupal\search_api\Item\FieldInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\elasticsearch_connector\ElasticSearch\Parameters\Factory\MappingFactory;

/**
 * @coversDefaultClass \Drupal\elasticsearch_connector\ElasticSearch\Parameters\Factory\MappingFactory
 *
 * @group elasticsearch_connector
 */
class MappingFactoryTest extends UnitTestCase {

  /**
   * @covers ::mappingFromField
   */
  public function testMappingFromField() {
    /** @var \Prophecy\Prophecy\ObjectProphecy $field_prophecy */
    $field = $this->prophesize(FieldInterface::class);
    $field->getType()
      ->willReturn('text');
    $field->getBoost()
      ->willReturn(1);

    $expected_mapping = [
      'type' => 'text',
      'boost' => 1,
      'fields' => [
        "keyword" => [
          "type" => 'keyword',
          'ignore_above' => 256,
        ]
      ]
    ];
    $this->assertEquals($expected_mapping, MappingFactory::mappingFromField($field->reveal()));

    /** @var \Prophecy\Prophecy\ObjectProphecy $field_prophecy */
    $field = $this->prophesize(FieldInterface::class);
    $field->getType()
      ->willReturn('uri');

    $expected_mapping = [
      'type' => 'keyword',
    ];
    $this->assertEquals($expected_mapping, MappingFactory::mappingFromField($field->reveal()));

    /** @var \Prophecy\Prophecy\ObjectProphecy $field_prophecy */
    $field = $this->prophesize(FieldInterface::class);
    $field->getType()
      ->willReturn('integer');

    $expected_mapping = [
      'type' => 'integer',
    ];
    $this->assertEquals($expected_mapping, MappingFactory::mappingFromField($field->reveal()));

    /** @var \Prophecy\Prophecy\ObjectProphecy $field_prophecy */
    $field = $this->prophesize(FieldInterface::class);
    $field->getType()
      ->willReturn('boolean');

    $expected_mapping = [
      'type' => 'boolean',
    ];
    $this->assertEquals($expected_mapping, MappingFactory::mappingFromField($field->reveal()));

    /** @var \Prophecy\Prophecy\ObjectProphecy $field_prophecy */
    $field = $this->prophesize(FieldInterface::class);
    $field->getType()
      ->willReturn('decimal');

    $expected_mapping = [
      'type' => 'float',
    ];
    $this->assertEquals($expected_mapping, MappingFactory::mappingFromField($field->reveal()));

    /** @var \Prophecy\Prophecy\ObjectProphecy $field_prophecy */
    $field = $this->prophesize(FieldInterface::class);
    $field->getType()
      ->willReturn('date');

    $expected_mapping = [
      'type' => 'date',
      'format' => 'epoch_second',
    ];
    $this->assertEquals($expected_mapping, MappingFactory::mappingFromField($field->reveal()));

    /** @var \Prophecy\Prophecy\ObjectProphecy $field_prophecy */
    $field = $this->prophesize(FieldInterface::class);
    $field->getType()
      ->willReturn('attachment');

    $expected_mapping = [
      'type' => 'attachment',
    ];
    $this->assertEquals($expected_mapping, MappingFactory::mappingFromField($field->reveal()));

    /** @var \Prophecy\Prophecy\ObjectProphecy $field_prophecy */
    $field = $this->prophesize(FieldInterface::class);
    $field->getType()
      ->willReturn('object');

    $expected_mapping = [
      'type' => 'nested',
    ];
    $this->assertEquals($expected_mapping, MappingFactory::mappingFromField($field->reveal()));

    /** @var \Prophecy\Prophecy\ObjectProphecy $field_prophecy */
    $field = $this->prophesize(FieldInterface::class);
    $field->getType()
      ->willReturn('foo');

    $this->assertNull(MappingFactory::mappingFromField($field->reveal()));
  }

}
