<?php

/**
 * @file
 * Contains \Drupal\elasticsearch\Tests\ElasticsearchTest.
 */

namespace Drupal\elasticsearch\Tests;

use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api\Tests\ExampleContentTrait;
use Drupal\search_api_db\Tests\SearchApiDbTest;

/**
 * Tests index and search capabilities using the elasticsearch backend.
 */
class ElasticsearchTest extends SearchApiDbTest {

  /**
   * A Search API server ID.
   *
   * @var string
   */
  protected $serverId = 'elasticsearch_server';

  /**
   * A Search API index ID.
   *
   * @var string
   */
  protected $indexId = 'elasticsearch_index';

  protected $elasticsearchAvailable = FALSE;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('elasticsearch', 'elasticsearch_test');

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Test "Elasticsearch" module',
      'description' => 'Tests indexing and searching with the "Elasticsearch" module.',
      'group' => 'Search API',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->installConfig(array('elasticsearch_test'));

    try {
      $server = Server::load($this->serverId);
      if ($server->getBackend()->ping()) {
        $this->elasticsearchAvailable = TRUE;
      }
    }
    catch (\Exception $e) {
    }
  }

  /**
   * Tests various indexing scenarios for the Elasticsearch backend as given in search_api_db.
   */
  public function testFramework() {
    if ($this->elasticsearchAvailable) {
      parent::testFramework();
    }
    else {
      $this->pass('Error: The Elasticsearch instance could not be found.');
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function indexItems($index_id) {
    /** @var \Drupal\search_api\Index\IndexInterface $index */
    $index = Index::load($index_id);
    $index->setOption('index_directly', TRUE);
    return $index->index();
  }

  /**
   * {@inheritdoc}
   */
  protected function clearIndex() {
    $server = Server::load($this->serverId);
    $index = Index::load($this->indexId);
    $index->clear();
    // manual commit
    $server->getBackend()->commit();
  }

  /**
   * {@inheritdoc}
   */
  protected function uninstallModule() {
    // See whether clearing the server works.
    // Regression test for #2156151.
    $server = Server::load($this->serverId);
    $index = Index::load($this->indexId);
    $server->deleteAllItems($index);

    // manual commit
    $server->getBackend()->commit();

    $query = $this->buildSearch();
    $results = $query->execute();
    $this->assertEqual($results->getResultCount(), 0, 'Clearing the server worked correctly.');
  }

  // search_api_db tests to be overridden
  protected function checkServerTables() {
  }

  protected function updateIndex() {
  }

  protected function editServer() {
  }

  protected function searchSuccess2() {
  }

  protected function assertIgnored(ResultSetInterface $results, array $ignored = array(), $message = 'No keys were ignored.') {
  }
}
