<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Site\Extension;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Component\Router\RouterInterface;
use Joomla\CMS\Component\Router\RouterServiceInterface;
use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\Component\Nxpeasyforms\Site\Service\Router;
use Psr\Container\ContainerInterface;


// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Component entry point for the site application.
 */
final class NxpeasyformsComponent extends MVCComponent implements BootableExtensionInterface, RouterServiceInterface
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

    }

    /**
     * {@inheritDoc}
     */
    public function getRouter(?SiteApplication $app = null, ?AbstractMenu $menu = null)
    {
        $app  = $app ?? Factory::getApplication();
        $menu = $menu ?? $app->getMenu();
        // Avoid forcing DI registration; Router will resolve repository with its own fallback
        return new Router($app, $menu, null);
    }

    /**
     * {@inheritDoc}
     */
    public function createRouter(CMSApplicationInterface $application, AbstractMenu $menu): RouterInterface
    {
        $app = $application instanceof SiteApplication ? $application : Factory::getApplication();

        return new Router($app, $menu, null);
    }
}
