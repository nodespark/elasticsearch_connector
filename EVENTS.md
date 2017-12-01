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
