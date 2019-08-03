<?php

namespace Drupal\Tests\elasticsearch_connector\Unit\Entity;

use Drupal\Tests\UnitTestCase;
use Drupal\elasticsearch_connector\Entity\Index;

/**
 * @coversDefaultClass \Drupal\elasticsearch_connector\Entity\Index
 *
 * @group elasticsearch_connector
 */
class IndexTest extends UnitTestCase {

  /**
   * @covers ::id
   */
  public function testId() {
    $index = new Index(['index_id' => 'foo'], 'elasticsearch_index');
    $this->assertEquals('foo', $index->id());
  }

}
