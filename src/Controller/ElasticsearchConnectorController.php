<?php

/**
 * @file
 * Contains \Drupal\elasticsearch_connector\Controller\ElasticsearchConnectorController
 */

namespace Drupal\elasticsearch_connector\Controller;

use Drupal\elasticsearch_connector\ClusterInterface;
use Drupal\Component\Utility\Xss;

/**
 * Example page controller.
 */
class ElasticsearchConnectorController {
  /**
   * Generates an example page.
   */
  public function adminClusters() {
    return array(
      '#markup' => t('TODO: Build the required logic here!'),
    );
  }

  public function clusterTitle($cluster = null) {
    //$cluster->label()
    return Xss::filter('Test');
  }

}
?>