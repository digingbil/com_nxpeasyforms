<?php

declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\DI\Container;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\Extension\Service\Provider\RouterFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Router\RouterFactoryInterface;

return static function (Container $container): void {
    $container->registerServiceProvider(new ComponentDispatcherFactory('com_nxpeasyforms'));
    $container->registerServiceProvider(new MVCFactory('Joomla\\Component\\Nxpeasyforms'));
    $container->registerServiceProvider(new RouterFactory('Joomla\\Component\\Nxpeasyforms'));

    $container->set(
        ComponentInterface::class,
        static function (Container $container): ComponentInterface {
            return new \Joomla\Component\Nxpeasyforms\Site\Extension\NxpeasyformsComponent(
                $container->get(ComponentDispatcherFactoryInterface::class),
                $container->get(MVCFactoryInterface::class),
                $container->get(RouterFactoryInterface::class)
            );
        }
    );
};
