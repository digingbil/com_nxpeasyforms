<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Site\Controller;

use Joomla\CMS\MVC\Controller\BaseController;


// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Default controller for site-facing requests.
 */
final class DisplayController extends BaseController
{
    /**
     * Default view name for the site application.
     *
     * @var string
     */
    protected $default_view = 'form';
}
