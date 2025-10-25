<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Controller;

use Joomla\CMS\MVC\Controller\FormController as JoomlaFormController;


// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Controller for a single form (builder view).
 * @since 1.0.0
 */
final class FormController extends JoomlaFormController
{
    protected $text_prefix = 'COM_NXPEASYFORMS';
}
