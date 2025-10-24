<?php

declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\DI\Container;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Repository\FormRepository;
use Joomla\Component\Nxpeasyforms\Site\Extension\NxpeasyformsComponent;
use Joomla\Database\DatabaseDriver;
use Joomla\DI\ServiceProviderInterface;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->registerServiceProvider(new MVCFactory('\\Joomla\\Component\\Nxpeasyforms'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('\\Joomla\\Component\\Nxpeasyforms'));

        $container->set(
            ComponentInterface::class,
            static function (Container $container): ComponentInterface {
                if (!class_exists(NxpeasyformsComponent::class)) {
                    \JLoader::registerNamespace(
                        'Joomla\\Component\\Nxpeasyforms\\Site',
                        dirname(__DIR__) . '/src'
                    );
                }

                $component = new NxpeasyformsComponent(
                    $container->get(ComponentDispatcherFactoryInterface::class)
                );

                $component->setMVCFactory($container->get(MVCFactoryInterface::class));

                return $component;
            }
        );

        $container->share(
            FormRepository::class,
            static function (Container $container): FormRepository {
                return new FormRepository($container->get(DatabaseDriver::class));
            }
        );
    }
};
