<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Controller;

use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;


// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Controller for the forms list view.
 * @since 1.0.0
 */
final class FormsController extends AdminController
{
    protected $text_prefix = 'COM_NXPEASYFORMS';

    protected $view_list = 'forms';

    protected $view_item = 'form';

    /**
     * Get and return a model instance.
     *
     * The method returns a named model instance from the MVC factory. Callers
     * can supply the model name, class prefix and configuration.
     *
     * @param string $name   The model name. Defaults to 'Form'.
     * @param string $prefix The class prefix for the model.
     * @param array<string,mixed> $config Configuration options for model creation.
     *
     * @return BaseDatabaseModel
     * @since 1.0.0
     */
    public function getModel($name = 'Form', $prefix = 'Joomla\\Component\\Nxpeasyforms\\Administrator\\Model\\', $config = ['ignore_request' => true]): BaseDatabaseModel
    {
        return parent::getModel($name, $prefix, $config);
    }
}
