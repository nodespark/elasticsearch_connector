<?php

namespace Drupal\elasticsearch\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\views_ui\ViewListBuilder;
use Drupal\node\NodeTypeListBuilder;

class Cluster extends ConfigEntityBase implements ClusterStorageInterface {
  public $id;
  public $label;
  public $status;
  public $description;
  protected $locked = FALSE;
}
