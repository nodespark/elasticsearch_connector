# ElasticSearch Event

## PrepareIndexEvent
This event is fired just before the Index is created. If you need to alter the index, you can implement an EventSubscriber like so:

### Create your EventSubscriber
Create a new class in ``mymodule_search/src/EventSubscriber/PrepareIndex.php``

```
<?php

namespace Drupal\mymodule_search\EventSubscriber;

use Drupal\elasticsearch_connector\Event\PrepareIndexEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * {@inheritdoc}
 */
class PrepareIndex implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[PrepareIndexEvent::PREPARE_INDEX] = 'prepareIndex';
    return $events;
  }

  /**
   * Method to prepare index.
   *
   * @param Drupal\elasticsearch_connector\Event\PrepareIndexEvent $event
   *   The PrepareIndexEvent event.
   */
  public function prepareIndex(PrepareIndexEvent $event) {
    $indexConfig = $event->getIndexConfig();

    $indexConfig['body']['settings']['analysis'] = [
      'filter' => [
        'synonym' => [
          'type' => 'synonym',
          'synonyms_path' => 'analysis/synonym.txt',
          'tokenizer' => 'whitespace'
        ]
      ],
      'analyzer' => [
        'synonym' => [
          'tokenizer' => 'whitespace',
          'filter' => [
            'lowercase',
            'synonym'
          ]
        ]
      ]
    ];

    $event->setIndexConfig($indexConfig);
  }

}

```

### Define a new EventSubscriber Service
Create a new class in ``mymodule_search/mymodule_search.yml``

```
services:
  mymodule_search.prepare_index:
    class: Drupal\mymodule_search\EventSubscriber\PrepareIndex
    tags:
      - { name: event_subscriber }

```

## PrepareSearchQueryEvent
This event is fired just before the ElasticSearch Query is created. If you need to alter the query, you can implement an EventSubscriber like so:

### Create your EventSubscriber
Create a new class in ``mymodule_search/src/EventSubscriber/PrepareQuery.php``

```
<?php

namespace Drupal\mymodule_search\EventSubscriber;

use Drupal\elasticsearch_connector\Event\PrepareSearchQueryEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * {@inheritdoc}
 */
class PrepareQuery implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[PrepareSearchQueryEvent::PREPARE_QUERY] = 'prepareQuery';
    return $events;
  }

  /**
   * Method to prepare query.
   *
   * @param Drupal\elasticsearch_connector\Event\PrepareSearchQueryEvent $event
   *   The PrepareSearchQueryEvent event.
   */
  public function prepareQuery(PrepareSearchQueryEvent $event) {
    $elasticSearchQuery = $event->getElasticSearchQuery();
    $elasticSearchQuery['query_search_string']['query_string']['analyzer'] = 'synonym';

    $event->setElasticSearchQuery($elasticSearchQuery);
  }

}


```

### Define a new EventSubscriber Service
Create a new class in ``mymodule_search/mymodule_search.yml``

```
services:
  mymodule_search.prepare_query:
    class: Drupal\mymodule_search\EventSubscriber\PrepareQuery
    tags:
      - { name: event_subscriber }
```
