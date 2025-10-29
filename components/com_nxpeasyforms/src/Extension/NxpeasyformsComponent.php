<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Site\Extension;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Repository\FormRepository;
use Joomla\Component\Nxpeasyforms\Site\Service\Router;
use Psr\Container\ContainerInterface;


// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Component entry point for the site application.
 */
final class NxpeasyformsComponent extends MVCComponent implements BootableExtensionInterface
{
    /**
     * Booting the extension. This is the function to set up the environment of the extension like
     * registering new class loaders, etc.
     *
     * @param   ContainerInterface  $container  The container
     *
     * @return  void
     *
     * @since   1.0.1
     */
    public function boot(ContainerInterface $container): void
    {
        // Register the Modal field subdirectory so menu XMLs can discover Modal_Form field
        $path = JPATH_ADMINISTRATOR . '/components/com_nxpeasyforms/src/Field/Modal';
        Form::addFieldPath($path);

        // Also try adding with addfieldprefix
        Form::addFormPath(JPATH_ADMINISTRATOR . '/components/com_nxpeasyforms/src/Field');

        error_log('NXP DEBUG: boot() called, added field path: ' . $path);
    }

    /**
     * {@inheritDoc}
     */
    public function getRouter(?SiteApplication $app = null, ?AbstractMenu $menu = null)
    {
        $app  = $app ?? Factory::getApplication();
        $menu = $menu ?? $app->getMenu();

        $forms = Factory::getContainer()->get(FormRepository::class);

        return new Router($app, $menu, $forms);
    }
}
