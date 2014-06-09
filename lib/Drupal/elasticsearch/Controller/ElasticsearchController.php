<?php

namespace Drupal\elasticsearch\Controller;

use Drupal\Core\Controller\ControllerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ElasticsearchController implements ControllerInterface {
	public static function create(ContainerInterface $container) {
		return new static($container->get('module_handler'));
	}
}
