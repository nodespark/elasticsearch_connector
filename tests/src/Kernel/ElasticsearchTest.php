<?php

namespace Drupal\elasticsearch_connector\Tests\Kernel;

use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Tests\search_api_db\Kernel\BackendTest;

/**
 * Tests index and search capabilities using the elasticsearch backend.
 *
 * @group elasticsearch_connector
 */
class ElasticsearchTest extends BackendTest {

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
  public static $modules = array('elasticsearch_connector', 'elasticsearch_test');

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
   * Tests various indexing scenarios for the Elasticsearch backend.
   *
   * As given in search_api_db.
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
    $prepareSearch = $this->buildSearch('test')
                          ->range(1, 2)
                          ->sort($this->getFieldId('id'), 'ASC');
    sleep(1);
    $results = $prepareSearch->execute();
    $this->assertEqual($results->getResultCount(), 4, 'Search for »test« returned correct number of results.');
    $this->assertEqual(
      array_keys($results->getResultItems()), $this->getItemIds(
      array(
        2,
        3,
      )
    ), 'Search for »test« returned correct result.'
    );
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $ids = $this->getItemIds(array(2));
    $id = reset($ids);
    $this->assertEqual(key($results->getResultItems()), $id);
    $this->assertEqual($results->getResultItems()[$id]->getId(), $id);
    $this->assertEqual($results->getResultItems()[$id]->getDatasourceId(), 'entity:entity_test');

    $prepareSearch = $this->buildSearch('test foo')
                          ->sort($this->getFieldId('id'), 'ASC');
    sleep(1);
    $results = $prepareSearch->execute();
    $this->assertEqual($results->getResultCount(), 3, 'Search for »test foo« returned correct number of results.');
    $this->assertEqual(
      array_keys(
        $results->getResultItems()
      ),
      $this->getItemIds(
        array(1, 2, 4)
      ),
      'Search for »test foo« returned correct result.'
    );
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $prepareSearch = $this->buildSearch('foo', array('type,item'))
                          ->sort($this->getFieldId('id'), 'ASC');
    sleep(1);
    $results = $prepareSearch->execute();
    $this->assertEqual($results->getResultCount(), 2, 'Search for »foo« returned correct number of results.');
    $this->assertEqual(
      array_keys($results->getResultItems()), $this->getItemIds(
      array(
        1,
        2,
      )
    ), 'Search for »foo« returned correct result.'
    );
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
  protected function checkModuleUninstall() {
    // See whether clearing the server works.
    // Regression test for #2156151.
    $server = Server::load($this->serverId);
    $index = Index::load($this->indexId);
    $server->getBackend()->removeIndex($index);

    $query = $this->buildSearch();
    $results = $query->execute();
    $this->assertEqual($results->getResultCount(), 0, 'Clearing the server worked correctly.');
  }

  /**
   * A search_api_db tests to be overridden.
   */
  protected function checkServerTables() {
  }

  /**
   * To be overridden.
   */
  protected function searchSuccess2() {
  }

  /**
   * Regression tests.
   */
  protected function regressionTests() {
    // Regression tests for #2007872.
    $prepareSearch = $this->buildSearch('test')
                          ->sort($this->getFieldId('id'), 'ASC')
                          ->sort($this->getFieldId('type'), 'ASC');
    $results = $prepareSearch->execute();
    $this->assertEqual($results->getResultCount(), 4, 'Sorting on field with NULLs returned correct number of results.');
    $this->assertEqual(
      array_keys(
        $results->getResultItems()
      ),
      $this->getItemIds(
        array(1, 2, 3, 4)
      ),
      'Sorting on field with NULLs returned correct result.'
    );
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
    $this->assertEqual(
      array_keys(
        $results->getResultItems()
      ),
      $this->getItemIds(
        array(3, 4, 5)
      ),
      'OR filter on field with NULLs returned correct result.'
    );
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
    $this->assertEqual(
      array_keys(
        $results->getResultItems()
      ),
      $this->getItemIds(
        array(1, 2, 4, 5)
      ),
      'OR filter on multi-valued field returned correct result.'
    );
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
    $this->assertEqual(
      array_keys(
        $results->getResultItems()
      ),
      $this->getItemIds(
        array(2, 4, 5)
      ),
      'Multiple OR filters on multi-valued field returned correct result.'
    );
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
    $this->assertEqual(
      array_keys(
        $results->getResultItems()
      ),
      $this->getItemIds(
        array(2, 4, 5)
      ),
      'Complex nested filters on multi-valued field returned correct result.'
    );
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
    $this->assertEqual(
      array_keys(
        $results->getResultItems()
      ),
      $this->getItemIds(
        array(1, 2, 4)
      ),
      'OR keywords returned correct result.'
    );
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $query = $this->buildSearch(
      $keys, array(), array(
        $this->getFieldId('name'),
        $this->getFieldId('body'),
      )
    );
    $query->range(0, 0);
    $results = $query->execute();
    $this->assertEqual($results->getResultCount(), 5, 'Multi-field OR keywords returned correct number of results.');
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
    $this->assertEqual(
      array_keys(
        $results->getResultItems()
      ),
      $this->getItemIds(
        array(1, 2, 4, 5)
      ),
      'Nested OR keywords returned correct result.'
    );
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
    $query = $this->buildSearch(
      $keys, array(), array(
        $this->getFieldId('name'),
        $this->getFieldId('body'),
      )
    );
    $query->sort($this->getFieldId('id'), 'ASC');
    $results = $query->execute();
    $this->assertEqual($results->getResultCount(), 4, 'Nested multi-field OR keywords returned correct number of results.');
    $this->assertEqual(
      array_keys(
        $results->getResultItems()
      ),
      $this->getItemIds(
        array(1, 2, 4, 5)
      ),
      'Nested multi-field OR keywords returned correct result.'
    );
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    // Regression tests for #2127001.
    $keys = array(
      '#conjunction' => 'AND',
      '#negation' => TRUE,
      'foo',
      'bar',
    );
    $results = $this->buildSearch($keys)
                    ->sort('search_api_id', 'ASC')
                    ->execute();
    $this->assertEqual($results->getResultCount(), 2, 'Negated AND fulltext search returned correct number of results.');
    $this->assertEqual(
      array_keys($results->getResultItems()), $this->getItemIds(
      array(
        3,
        4,
      )
    ), 'Negated AND fulltext search returned correct result.'
    );
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
    $results = $this->buildSearch($keys)
                    ->sort('search_api_id', 'ASC')
                    ->execute();
    $this->assertEqual($results->getResultCount(), 2, 'Nested NOT AND fulltext search returned correct number of results.');
    $this->assertEqual(
      array_keys($results->getResultItems()), $this->getItemIds(
      array(
        3,
        4,
      )
    ), 'Nested NOT AND fulltext search returned correct result.'
    );
    $this->assertIgnored($results);
    $this->assertWarnings($results);

  }

  /**
   * Regression Tests 2.
   */
  protected function regressionTests2() {
    // Create a "keywords" field on the test entity type.
    FieldStorageConfig::create(
      array(
        'field_name' => 'prices',
        'entity_type' => 'entity_test',
        'type' => 'decimal',
        'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      )
    )->save();
    FieldConfig::create(
      array(
        'field_name' => 'prices',
        'entity_type' => 'entity_test',
        'bundle' => 'item',
        'label' => 'Prices',
      )
    )->save();

    // Regression test for #1916474.
    /** @var \Drupal\search_api\Index\IndexInterface $index */
    $index = Index::load($this->indexId);
    $index->getFields(FALSE)[$this->getFieldId('prices')]->setType('decimal')
                                                         ->setIndexed(TRUE, TRUE);
    $success = $index->save();
    $this->assertTrue($success, 'The index field settings were successfully changed.');

    // Reset the static cache so the new values will be available.
    \Drupal::entityTypeManager()
           ->getStorage('search_api_server')
           ->resetCache(array($this->serverId));
    \Drupal::entityTypeManager()
           ->getStorage('search_api_index')
           ->resetCache(array($this->serverId));

    \Drupal::entityTypeManager()
          ->getStorage('entity_test')
          ->create(
            array(
              'id' => 6,
              'prices' => array('3.5', '3.25', '3.75', '3.5'),
              'type' => 'item',
            )
          )
      ->save();

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

  /**
   * A updateIndex tests.
   */
  protected function updateIndex() {
  }

  /**
   * A editServer tests.
   */
  protected function editServer() {
  }

  /**
   * A assertIgnored test.
   *
   * @param \Drupal\search_api\Query\ResultSetInterface $results
   *   Search results.
   * @param array $ignored
   *   What to be ignored.
   * @param string $message
   *   Result message.
   */
  protected function assertIgnored(ResultSetInterface $results, array $ignored = array(), $message = 'No keys were ignored.') {
  }

}
