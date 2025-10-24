<?php

declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Controller;

use Joomla\CMS\MVC\Controller\FormController as JoomlaFormController;

/**
 * Controller for a single form (builder view).
 */
final class FormController extends JoomlaFormController
{
    protected string $text_prefix = 'COM_NXPEASYFORMS';
}
