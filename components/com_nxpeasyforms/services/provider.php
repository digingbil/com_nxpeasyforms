<?php
declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\DI\Container;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Component\Nxpeasyforms\Site\Extension\NxpeasyformsComponent;
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

                if (!class_exists('Joomla\\Component\\Nxpeasyforms\\Api\\Extension\\NxpeasyformsComponent')) {
                    \JLoader::registerNamespace(
                        'Joomla\\Component\\Nxpeasyforms\\Api',
                        \JPATH_ROOT . '/api/components/com_nxpeasyforms/src'
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
