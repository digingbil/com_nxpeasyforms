<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Controller;

use Joomla\CMS\MVC\Controller\BaseController;


// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Default display controller for administrator routes.
 *
 * Routes bare component URLs to the forms list view so the component loads
 * cleanly when accessed from the Joomla administrator menu.
 */
final class DisplayController extends BaseController
{
    /**
     * Default view name for the administrator application.
     *
     * @var string
     */
    protected $default_view = 'forms';
}
