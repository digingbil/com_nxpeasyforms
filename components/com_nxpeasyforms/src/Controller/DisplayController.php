<?php

declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Site\Controller;

use Joomla\CMS\MVC\Controller\BaseController;

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
