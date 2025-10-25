<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Controller;

use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;


// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Controller for submissions list view.
 */
final class SubmissionsController extends AdminController
{
    protected $text_prefix = 'COM_NXPEASYFORMS';

    protected $view_list = 'submissions';

    /**
     * {@inheritDoc}
     */
    public function getModel($name = 'Submissions', $prefix = 'Joomla\\Component\\Nxpeasyforms\\Administrator\\Model\\', $config = ['ignore_request' => true]): BaseDatabaseModel
    {
        return parent::getModel($name, $prefix, $config);
    }
}
