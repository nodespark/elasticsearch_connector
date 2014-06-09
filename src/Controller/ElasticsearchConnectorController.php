<?php

/**
 * @file
 * Contains \Drupal\elasticsearch_connector\Controller\ElasticsearchConnectorController
 */

namespace Drupal\elasticsearch_connector\Controller;

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

  public function clusterTitle(ClusterInterface $cluster) {
    return Xss::filter($cluster->label());
  }

}
?>