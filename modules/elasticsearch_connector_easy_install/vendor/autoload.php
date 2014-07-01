<?php
/**
 * @file
 * Autoloader file required for Elasticsearch Connector Easy Install module.
 * It will load all required functions.
 */
$vendorDir = dirname(__FILE__);

$namespaces = array(
  'Symfony\\Component\\EventDispatcher\\' => array($vendorDir . '/symfony/event-dispatcher'),
  'Psr\\Log\\' => array($vendorDir . '/psr/log'),
  'Pimple' => array($vendorDir . '/pimple/pimple/src'),
  'Monolog' => array($vendorDir . '/monolog/monolog/src'),
  'Guzzle' => array($vendorDir . '/guzzle/guzzle/src'),
  'Elasticsearch' => array($vendorDir . '/elasticsearch/elasticsearch/src'),
);

$loader = new ElasticsearchConnectorEasyLoader();
foreach ($namespaces as $namespace => $path) {
  $loader->set($namespace, $path);
}

// Register the spl_autoload to search for the namespcases.
$loader->register(TRUE);

/**
 * Loader class used by the module in order to not download all required
 * libraries for this functionality.
 *
 * @class ElasticsearchConnectorEasyLoader
 * @author nikolayignatov
 *
 */
class ElasticsearchConnectorEasyLoader {
  private $prefixesPsr0 = array();
  private $fallbackDirsPsr0 = array();

  private $useIncludePath = false;
  private $classMap = array();

  /**
   *
   * @return mixed
   */
  public function getPrefixes() {
    return call_user_func_array('array_merge', $this->prefixesPsr0);
  }

  /**
   *
   * @return multitype:
   */
  public function getFallbackDirs() {
    return $this->fallbackDirsPsr0;
  }

  /**
   * Registers a set of PSR-0 directories for a given prefix,
   * replacing any others previously set for this prefix.
   *
   * @param string       $prefix The prefix
   * @param array|string $paths  The PSR-0 base directories
   */
  public function set($prefix, $paths) {
    if (!$prefix) {
      $this->fallbackDirsPsr0 = (array) $paths;
    } else {
      $this->prefixesPsr0[$prefix[0]][$prefix] = (array) $paths;
    }
  }

  /**
   * Registers this instance as an autoloader (PHP autoloader).
   *
   * @param bool $prepend Whether to prepend the autoloader or not
   */
  public function register($prepend = false) {
    spl_autoload_register(array($this, 'loadClass'), true, $prepend);
  }

  /**
   * Unregisters this instance as an autoloader (PHP autoloader).
   */
  public function unregister() {
    spl_autoload_unregister(array($this, 'loadClass'));
  }

  /**
   * Loads the given class or interface.
   *
   * @param  string    $class The name of the class
   * @return bool|null True if loaded, null otherwise
   */
  public function loadClass($class) {
    if ($file = $this->findFile($class)) {
      elasticsearch_connector_easy_install_load($file);
      return true;
    }
  }

  /**
   * Finds the path to the file where the class is defined.
   *
   * @param string $class The name of the class
   * @return string|false The path if found, false otherwise
   */
  public function findFile($class) {
    // work around - https://bugs.php.net/50731
    if ('\\' == $class[0]) {
      $class = substr($class, 1);
    }

    if (isset($this->classMap[$class])) {
      return $this->classMap[$class];
    }

    $logicalPathPsr = strtr($class, '\\', DIRECTORY_SEPARATOR) . '.php';
    $first = $class[0];

    if (false !== $pos = strrpos($class, '\\')) {
      // namespaced class name
      $logicalPathPsr0 = substr($logicalPathPsr, 0, $pos + 1)
      . strtr(substr($logicalPathPsr, $pos + 1), '_', DIRECTORY_SEPARATOR);
    } else {
      // PEAR-like class name
      $logicalPathPsr0 = strtr($class, '_', DIRECTORY_SEPARATOR) . '.php';
    }

    if (isset($this->prefixesPsr0[$first])) {
      foreach ($this->prefixesPsr0[$first] as $prefix => $dirs) {
        if (0 === strpos($class, $prefix)) {
          foreach ($dirs as $dir) {
            if (file_exists($file = $dir . DIRECTORY_SEPARATOR . $logicalPathPsr0)) {
              return $file;
            }
          }
        }
      }
    }

    // Fallback
    foreach ($this->fallbackDirsPsr0 as $dir) {
      if (file_exists($file = $dir . DIRECTORY_SEPARATOR . $logicalPathPsr0)) {
        return $file;
      }
    }

    // Include paths.
    if ($this->useIncludePath && $file = stream_resolve_include_path($logicalPathPsr0)) {
      return $file;
    }

    // Remember that this class does not exist.
    return $this->classMap[$class] = false;
  }
}


