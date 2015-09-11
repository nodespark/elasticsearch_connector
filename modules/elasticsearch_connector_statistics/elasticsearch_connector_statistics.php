<?php

/**
 * @file
 * Handles counts of node views via Ajax with minimal bootstrap.
 */

/**
* Root directory of Drupal installation.
*/

$current_path = getcwd();
$matches = array();
if (preg_match('(/sites/.*/elasticsearch_connector/modules/elasticsearch_connector_statistics)', $current_path, $matches)) {
  define('DRUPAL_ROOT', substr($_SERVER['SCRIPT_FILENAME'], 0, strpos($_SERVER['SCRIPT_FILENAME'], $matches[0])));
  // Set the scriptname to be /index.php in order to have the session correctly loaded.
  // TODO: hardcoding it in such way do not seems correct. We need to add the base_path if different from /.

  $_SERVER['SCRIPT_NAME'] = '/index.php';
  // Change the directory to the Drupal root.
  chdir(DRUPAL_ROOT);

  $img_url = $current_path . '/img/pixel.gif';
  // TODO: Send no cache headers and change this to use Drupal functions.
  header("Content-Length:" . sprintf('%u', filesize($img_url)));
  header("Content-Type:image/gif");
  $fd = fopen($img_url, 'rb');
  if ($fd) {
    while (!feof($fd)) {
      print fread($fd, 1024);
    }
    fclose($fd);
  }

  if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
  }
  // TODO: Lightweight bootstrap if possible.
  require_once DRUPAL_ROOT . '/includes/bootstrap.inc';
  drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
  elasticsearch_connector_statistics_log_statistics();
}
else {
  drupal_fast_404();
}