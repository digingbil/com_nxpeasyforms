<?php

declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Controller;

use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * Controller for the forms list view.
 */
final class FormsController extends AdminController
{
    protected $text_prefix = 'COM_NXPEASYFORMS';

    protected $view_list = 'forms';

    protected $view_item = 'form';

    /**
     * {@inheritDoc}
     */
    public function getModel($name = 'Form', $prefix = 'Joomla\\Component\\Nxpeasyforms\\Administrator\\Model\\', $config = ['ignore_request' => true]): BaseDatabaseModel
    {
        return parent::getModel($name, $prefix, $config);
    }
}
