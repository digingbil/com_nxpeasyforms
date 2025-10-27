<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Site\Extension;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Repository\FormRepository;
use Joomla\Component\Nxpeasyforms\Site\Service\Router;


// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Component entry point for the site application.
 */
final class NxpeasyformsComponent extends MVCComponent
{
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
