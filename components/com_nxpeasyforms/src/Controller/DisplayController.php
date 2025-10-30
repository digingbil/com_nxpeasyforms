<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Site\Controller;

use Joomla\CMS\MVC\Controller\BaseController;


// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Default display controller for site-facing requests.
 *
 * @since 1.0.0
 */
class DisplayController extends BaseController
{
    /**
     * Default view name for the site application.
     *
     * @var string
     * @since 1.0.0
     */
    protected $default_view = 'form';
}
