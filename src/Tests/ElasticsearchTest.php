<?php

/**
 * @file
 * Contains \Drupal\elasticsearch\Tests\ElasticsearchTest.
 */

namespace Drupal\elasticsearch\Tests;

use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\Query\ResultSetInterface;
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
      /** @var \Drupal\search_api\Entity\Server $server */
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
    $server->getBackend()->removeIndex($index);
  }

  /**
   * Tests whether some test searches have the correct results.
   */
  protected function searchSuccess1() {
    $prepareSearch = $this->buildSearch('test')->range(1, 2)->sort($this->getFieldId('id'), 'ASC');
    sleep(1);
    $results = $prepareSearch->execute();
    $this->assertEqual($results->getResultCount(), 4, 'Search for »test« returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(2, 3)), 'Search for »test« returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $ids = $this->getItemIds(array(2));
    $id = reset($ids);
    $this->assertEqual(key($results->getResultItems()), $id);
    $this->assertEqual($results->getResultItems()[$id]->getId(), $id);
    $this->assertEqual($results->getResultItems()[$id]->getDatasourceId(), 'entity:entity_test');

    $prepareSearch = $this->buildSearch('test foo')->sort($this->getFieldId('id'), 'ASC');
    sleep(1);
    $results = $prepareSearch->execute();
    $this->assertEqual($results->getResultCount(), 3, 'Search for »test foo« returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(1, 2, 4)), 'Search for »test foo« returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $prepareSearch = $this->buildSearch('foo', array('type,item'))->sort($this->getFieldId('id'), 'ASC');
    sleep(1);
    $results = $prepareSearch->execute();
    $this->assertEqual($results->getResultCount(), 2, 'Search for »foo« returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(1, 2)), 'Search for »foo« returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $keys = array(
      '#conjunction' => 'AND',
      'test',
      array(
        '#conjunction' => 'OR',
        'baz',
        'foobar',
      ),
      array(
        '#conjunction' => 'OR',
        '#negation' => TRUE,
        'bar',
        'fooblob',
      ),
    );
    $prepareSearch = $this->buildSearch($keys);
    sleep(1);
    $results = $prepareSearch->execute();
    $this->assertEqual($results->getResultCount(), 1, 'Complex search 1 returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(4)), 'Complex search 1 returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);
  }

  /**
   * {@inheritdoc}
   */
  protected function uninstallModule() {
    /** @var \Drupal\search_api\Entity\Server $server */
    $server = Server::load($this->serverId);
    /** @var \Drupal\search_api\Entity\Index $index */
    $index = Index::load($this->indexId);
    $server->getBackend()->removeIndex($index);

    $query = $this->buildSearch();
    $results = $query->execute();
    $this->assertEqual($results->getResultCount(), 0, 'Clearing the server worked correctly.');
  }

  // search_api_db tests to be overridden
  protected function checkServerTables() {
  }

  protected function searchSuccess2() {
  }

  protected function regressionTests() {
  }

  protected function regressionTests2() {
  }

  protected function updateIndex() {
  }

  protected function editServer() {
  }

  protected function assertIgnored(ResultSetInterface $results, array $ignored = array(), $message = 'No keys were ignored.') {
  }
}
