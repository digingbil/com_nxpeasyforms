<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Component\Nxpeasyforms\Api\Extension\NxpeasyformsComponent;
use Joomla\DI\Container;
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
                        'Joomla\\Component\\Nxpeasyforms\\Api',
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

        $registerDomainServices = include \JPATH_ADMINISTRATOR . '/components/com_nxpeasyforms/services/domain-services.php';
        $registerDomainServices($container);
    }
};
