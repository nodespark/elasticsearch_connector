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
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldInstanceConfig;

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
/*
    $prepareSearch = $this->buildSearch('test')->range(1, 2);
    sleep(1);
    $results = $prepareSearch->execute();
    $this->assertEqual($results->getResultCount(), 4, 'Search for »test« returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(4, 1)), 'Search for »test« returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $prepareSearch = $this->buildSearch(NULL, array('body,test foobar'));
    sleep(1);
    $results = $prepareSearch->execute();
    $this->assertEqual($results->getResultCount(), 1, 'Search with multi-term fulltext filter returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(3)), 'Search with multi-term fulltext filter returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $prepareSearch = $this->buildSearch('test foo');
    sleep(1);
    $results = $prepareSearch->execute();
    $this->assertEqual($results->getResultCount(), 4, 'Search for »test foo« returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(2, 4, 1, 3)), 'Search for »test foo« returned correct result.');
    $this->assertIgnored($results, array('foo'), 'Short key was ignored.');
    $this->assertWarnings($results);

    $prepareSearch = $this->buildSearch('foo', array('type,item'));
    sleep(1);
    $results = $prepareSearch->execute();
    $this->assertEqual($results->getResultCount(), 3, 'Search for »foo« returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(1, 2, 3)), 'Search for »foo« returned correct result.');
    $this->assertIgnored($results, array('foo'), 'Short key was ignored.');
    $this->assertWarnings($results, array($this->t('No valid search keys were present in the query.')), 'No warnings were displayed.');

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
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(3)), 'Complex search 1 returned correct result.');
    $this->assertIgnored($results, array('baz', 'bar'), 'Correct keys were ignored.');
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
    $this->assertEqual($results->getResultCount(), 1, 'Complex search 2 returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(3)), 'Complex search 2 returned correct result.');
    $this->assertIgnored($results, array('baz', 'bar'), 'Correct keys were ignored.');
    $this->assertWarnings($results);

    $prepareSearch = $this->buildSearch(NULL, array('keywords,orange'));
    sleep(1);
    $results = $prepareSearch->execute();
    $this->assertEqual($results->getResultCount(), 3, 'Filter query 1 on multi-valued field returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(1, 2, 5)), 'Filter query 1 on multi-valued field returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $filters = array(
      'keywords,orange',
      'keywords,apple',
    );
    $prepareSearch = $this->buildSearch(NULL, $filters);
    sleep(1);
    $results = $prepareSearch->execute();
    $this->assertEqual($results->getResultCount(), 1, 'Filter query 2 on multi-valued field returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(2)), 'Filter query 2 on multi-valued field returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $prepareSearch = $this->buildSearch()->condition($this->getFieldId('keywords'), NULL);
    sleep(1);
    $results = $prepareSearch->execute();
    $this->assertEqual($results->getResultCount(), 1, 'Query with NULL filter returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(3)), 'Query with NULL filter returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);
*/
  }

  protected function regressionTests() {
    // Regression tests for #2007872.
    $prepareSearch = $this->buildSearch('test')->sort($this->getFieldId('id'), 'ASC')->sort($this->getFieldId('type'), 'ASC');
    $results = $prepareSearch->execute();
    $this->assertEqual($results->getResultCount(), 4, 'Sorting on field with NULLs returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(1, 2, 3, 4)), 'Sorting on field with NULLs returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $query = $this->buildSearch();
    $filter = $query->createFilter('OR');
    $filter->condition($this->getFieldId('id'), 3);
    $filter->condition($this->getFieldId('type'), 'article');
    $query->filter($filter);
    $query->sort($this->getFieldId('id'), 'ASC');
    $results = $query->execute();
    $this->assertEqual($results->getResultCount(), 3, 'OR filter on field with NULLs returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(3, 4, 5)), 'OR filter on field with NULLs returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    // Regression tests for #1863672.
    $query = $this->buildSearch();
    $filter = $query->createFilter('OR');
    $filter->condition($this->getFieldId('keywords'), 'orange');
    $filter->condition($this->getFieldId('keywords'), 'apple');
    $query->filter($filter);
    $query->sort($this->getFieldId('id'), 'ASC');
    $results = $query->execute();
    $this->assertEqual($results->getResultCount(), 4, 'OR filter on multi-valued field returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(1, 2, 4, 5)), 'OR filter on multi-valued field returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $query = $this->buildSearch();
    $filter = $query->createFilter('OR');
    $filter->condition($this->getFieldId('keywords'), 'orange');
    $filter->condition($this->getFieldId('keywords'), 'strawberry');
    $query->filter($filter);
    $filter = $query->createFilter('OR');
    $filter->condition($this->getFieldId('keywords'), 'apple');
    $filter->condition($this->getFieldId('keywords'), 'grape');
    $query->filter($filter);
    $query->sort($this->getFieldId('id'), 'ASC');
    $results = $query->execute();
    $this->assertEqual($results->getResultCount(), 3, 'Multiple OR filters on multi-valued field returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(2, 4, 5)), 'Multiple OR filters on multi-valued field returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $query = $this->buildSearch();
    $filter1 = $query->createFilter('OR');
    $filter = $query->createFilter('AND');
    $filter->condition($this->getFieldId('keywords'), 'orange');
    $filter->condition($this->getFieldId('keywords'), 'apple');
    $filter1->filter($filter);
    $filter = $query->createFilter('AND');
    $filter->condition($this->getFieldId('keywords'), 'strawberry');
    $filter->condition($this->getFieldId('keywords'), 'grape');
    $filter1->filter($filter);
    $query->filter($filter1);
    $query->sort($this->getFieldId('id'), 'ASC');
    $results = $query->execute();
    $this->assertEqual($results->getResultCount(), 3, 'Complex nested filters on multi-valued field returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(2, 4, 5)), 'Complex nested filters on multi-valued field returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    // Regression tests for #2111753.
    $keys = array(
      '#conjunction' => 'OR',
      'foo',
      'test',
    );
    $query = $this->buildSearch($keys, array(), array($this->getFieldId('name')));
    $query->sort($this->getFieldId('id'), 'ASC');
    $results = $query->execute();
    $this->assertEqual($results->getResultCount(), 3, 'OR keywords returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(1, 2, 4)), 'OR keywords returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $query = $this->buildSearch($keys, array(), array($this->getFieldId('name'), $this->getFieldId('body')));
    $query->range(0, 0);
    $results = $query->execute();
    $this->assertEqual($results->getResultCount(), 5, 'Multi-field OR keywords returned correct number of results.');
    //$this->assertFalse($results->getResultItems(), 'Multi-field OR keywords returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $keys = array(
      '#conjunction' => 'OR',
      'foo',
      'test',
      array(
        '#conjunction' => 'AND',
        'bar',
        'baz',
      ),
    );
    $query = $this->buildSearch($keys, array(), array($this->getFieldId('name')));
    $query->sort($this->getFieldId('id'), 'ASC');
    $results = $query->execute();
    $this->assertEqual($results->getResultCount(), 4, 'Nested OR keywords returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(1, 2, 4, 5)), 'Nested OR keywords returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $keys = array(
      '#conjunction' => 'OR',
      array(
        '#conjunction' => 'AND',
        'foo',
        'test',
      ),
      array(
        '#conjunction' => 'AND',
        'bar',
        'baz',
      ),
    );
    $query = $this->buildSearch($keys, array(), array($this->getFieldId('name'), $this->getFieldId('body')));
    $query->sort($this->getFieldId('id'), 'ASC');
    $results = $query->execute();
    $this->assertEqual($results->getResultCount(), 4, 'Nested multi-field OR keywords returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(1, 2, 4, 5)), 'Nested multi-field OR keywords returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    // Regression tests for #2127001.
    $keys = array(
      '#conjunction' => 'AND',
      '#negation' => TRUE,
      'foo',
      'bar',
    );
    $results = $this->buildSearch($keys)->sort('search_api_id', 'ASC')->execute();
    $this->assertEqual($results->getResultCount(), 2, 'Negated AND fulltext search returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(3, 4)), 'Negated AND fulltext search returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $keys = array(
      '#conjunction' => 'OR',
      '#negation' => TRUE,
      'foo',
      'baz',
    );
    $results = $this->buildSearch($keys)->execute();
    $this->assertEqual($results->getResultCount(), 1, 'Negated OR fulltext search returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(3)), 'Negated OR fulltext search returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $keys = array(
      '#conjunction' => 'AND',
      'test',
      array(
        '#conjunction' => 'AND',
        '#negation' => TRUE,
        'foo',
        'bar',
      ),
    );
    $results = $this->buildSearch($keys)->sort('search_api_id', 'ASC')->execute();
    $this->assertEqual($results->getResultCount(), 2, 'Nested NOT AND fulltext search returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(3, 4)), 'Nested NOT AND fulltext search returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

  }

  protected function regressionTests2() {
    // Create a "keywords" field on the test entity type.
    FieldStorageConfig::create(array(
      'name' => 'prices',
      'entity_type' => 'entity_test',
      'type' => 'decimal',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ))->save();
    FieldInstanceConfig::create(array(
      'field_name' => 'prices',
      'entity_type' => 'entity_test',
      'bundle' => 'item',
      'label' => 'Prices',
    ))->save();

    // Regression test for #1916474.
    /** @var \Drupal\search_api\Index\IndexInterface $index */
    $index = Index::load($this->indexId);
    $index->getFields(FALSE)[$this->getFieldId('prices')]->setType('decimal')->setIndexed(TRUE, TRUE);
    $success = $index->save();
    $this->assertTrue($success, 'The index field settings were successfully changed.');

    // Reset the static cache so the new values will be available.
    \Drupal::entityManager()->getStorage('search_api_server')->resetCache(array($this->serverId));
    \Drupal::entityManager()->getStorage('search_api_index')->resetCache(array($this->serverId));

    entity_create('entity_test', array(
      'id' => 6,
      'prices' => array('3.5', '3.25', '3.75', '3.5'),
      'type' => 'item',
    ))->save();

    $this->indexItems($this->indexId);

    $query = $this->buildSearch(NULL, array('prices,3.25'));
    sleep(1);
    $results = $query->execute();
    $this->assertEqual($results->getResultCount(), 1, 'Filter on decimal field returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(6)), 'Filter on decimal field returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $query = $this->buildSearch(NULL, array('prices,3.50'));
    sleep(1);
    $results = $query->execute();
    $this->assertEqual($results->getResultCount(), 1, 'Filter on decimal field returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(6)), 'Filter on decimal field returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);
  }


  protected function updateIndex() {
  }

  protected function editServer() {
  }

  protected function assertIgnored(ResultSetInterface $results, array $ignored = array(), $message = 'No keys were ignored.') {
  }
}
