<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
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
