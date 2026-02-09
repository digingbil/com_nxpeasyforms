<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */
declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/*
 * PSR-16 SimpleCache polyfill - register namespace for autoloading if psr/simple-cache is not installed.
 * This allows the interfaces to be autoloaded when first referenced.
 */
if (!interface_exists('Psr\SimpleCache\CacheInterface', false)) {
    \JLoader::registerNamespace('Psr\SimpleCache', dirname(__DIR__) . '/src/Polyfill/PsrSimpleCache', false, false, 'psr4');
}

use Joomla\CMS\Component\Router\RouterFactoryInterface;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\DI\Container;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\Extension\Service\Provider\RouterFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Factory as JFactoryAlias;
use Joomla\Component\Nxpeasyforms\Administrator\Extension\NxpeasyformsComponent as AdminComponent;
use Joomla\Component\Nxpeasyforms\Site\Extension\NxpeasyformsComponent as SiteComponent;
use Joomla\DI\ServiceProviderInterface;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        // Ensure Site namespace is registered for Router
        if (!class_exists('Joomla\\Component\\Nxpeasyforms\\Site\\Service\\Router', false)) {
            \JLoader::registerNamespace(
                'Joomla\\Component\\Nxpeasyforms\\Site',
                \JPATH_ROOT . '/components/com_nxpeasyforms/src'
            );
        }

        $container->registerServiceProvider(new MVCFactory('\\Joomla\\Component\\Nxpeasyforms'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('\\Joomla\\Component\\Nxpeasyforms'));
        $container->registerServiceProvider(new RouterFactory('\\Joomla\\Component\\Nxpeasyforms'));

        $container->set(
            ComponentInterface::class,
            static function (Container $container): ComponentInterface {
                // Ensure namespaces are registered for both admin and site contexts
                if (!class_exists(AdminComponent::class)) {
                    \JLoader::registerNamespace(
                        'Joomla\\Component\\Nxpeasyforms\\Administrator',
                        dirname(__DIR__) . '/src'
                    );
                }

                if (!class_exists(SiteComponent::class)) {
                    \JLoader::registerNamespace(
                        'Joomla\\Component\\Nxpeasyforms\\Site',
                        \JPATH_ROOT . '/components/com_nxpeasyforms/src'
                    );
                }

                // Only register API namespace if API files are installed
                $apiPath = \JPATH_ROOT . '/api/components/com_nxpeasyforms/src';
                if (!class_exists('Joomla\\Component\\Nxpeasyforms\\Api\\Extension\\NxpeasyformsComponent') && is_dir($apiPath)) {
                    \JLoader::registerNamespace(
                        'Joomla\\Component\\Nxpeasyforms\\Api',
                        $apiPath
                    );
                }

                // Pick the correct component based on current application client
                $app = JFactoryAlias::getApplication();
                $isSite = $app->isClient('site');

                $component = $isSite
                    ? new SiteComponent($container->get(ComponentDispatcherFactoryInterface::class))
                    : new AdminComponent($container->get(ComponentDispatcherFactoryInterface::class));

                $component->setMVCFactory($container->get(MVCFactoryInterface::class));

                // In site context, call boot() to register field paths and router dependencies
                if ($isSite && method_exists($component, 'boot')) {
                    /** @var Psr\Container\ContainerInterface|Container $container */
                    $component->boot($container);
                }

                return $component;
            }
        );

        $registerDomainServices = include __DIR__ . '/domain-services.php';
        $registerDomainServices($container);
    }
};
