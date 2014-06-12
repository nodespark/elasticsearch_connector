<?php

namespace Drupal\elasticsearch\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Elasticsearch\Client;
use Drupal\Component\Utility\UrlHelper;

class Index extends ConfigEntityBase implements ClusterInterface {

}