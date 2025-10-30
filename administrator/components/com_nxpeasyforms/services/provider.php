<?php
declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

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

                if (!class_exists('Joomla\\Component\\Nxpeasyforms\\Api\\Extension\\NxpeasyformsComponent')) {
                    \JLoader::registerNamespace(
                        'Joomla\\Component\\Nxpeasyforms\\Api',
                        \JPATH_ROOT . '/api/components/com_nxpeasyforms/src'
                    );
                }

                // Pick the correct component based on current application client
                $app = JFactoryAlias::getApplication();
                $isSite = method_exists($app, 'isClient') ? $app->isClient('site') : ($app->isSite() ?? false);

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
