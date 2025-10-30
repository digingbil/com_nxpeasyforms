<?php
// Joomla bootstrap
const _JEXEC = 1;
define('JPATH_BASE', '/var/www/html/j5.loc');
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/app.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;

$container = Factory::getContainer();
$app        = $container->get(SiteApplication::class);
Factory::$application = $app;

$app->initialise();

$route = Route::_('index.php?option=com_nxpeasyforms&view=form&id=1');

echo $route, "\n";
