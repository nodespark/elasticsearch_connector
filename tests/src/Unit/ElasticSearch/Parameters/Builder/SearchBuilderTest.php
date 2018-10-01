<?php

namespace Drupal\Tests\elasticsearch_connector\Unit\ElasticSearch\Parameters\Builder;

use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\elasticsearch_connector\ElasticSearch\Parameters\Builder\SearchBuilder;

/**
 * @coversDefaultClass \Drupal\elasticsearch_connector\ElasticSearch\Parameters\Builder\SearchBuilder
 *
 * @group elasticsearch_connector
 */
class SearchBuilderTest extends UnitTestCase {

  /**
   * An instance of the SearchBuilder class.
   *
   * @var \Drupal\elasticsearch_connector\ElasticSearch\Parameters\Builder\SearchBuilder
   */
  protected $searchBuilder;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $index = $this->prophesize(IndexInterface::class);
    $query = $this->prophesize(QueryInterface::class);
    $query->getIndex()
      ->willReturn($index->reveal());

    $this->searchBuilder = new SearchBuilder($query->reveal());
  }

  /**
   * @covers ::__construct
   */
  public function testConstruct() {
    $this->assertInstanceOf(SearchBuilder::class, $this->searchBuilder);
  }

  /**
   * @covers ::build
   */
  public function testBuild() {
    // TODO Can't test because IndexFactory is hardcoded
    // instead of injected so it can't be mocked.
  }
}
