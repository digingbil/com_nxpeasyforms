<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Controller;

use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Repository\FormRepository;
use Joomla\Database\DatabaseDriver;


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
    public function getModel($name = 'Form', $prefix = 'Administrator', $config = ['ignore_request' => true]): BaseDatabaseModel
    {
        $model = parent::getModel($name, $prefix, $config);

        if (!$model instanceof BaseDatabaseModel) {
            throw new \RuntimeException(Text::sprintf('JLIB_APPLICATION_ERROR_MODEL_CREATE', $name), 500);
        }

        return $model;
    }

    /**
     * Duplicate one or more selected forms.
     *
     * @return void
     */
    public function duplicate(): void
    {
        $this->checkToken();

        $app = Factory::getApplication();
        $ids = $this->input->get('cid', [], 'array');

        if (empty($ids)) {
            $app->enqueueMessage(Text::_('JERROR_NO_ITEMS_SELECTED'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_nxpeasyforms&view=forms', false));

            return;
        }

        $user = $app->getIdentity();

        if (!$user->authorise('core.create', 'com_nxpeasyforms')) {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_nxpeasyforms&view=forms', false));

            return;
        }

        $container = Factory::getContainer();

        if (method_exists($container, 'has') && $container->has(FormRepository::class)) {
            /** @var FormRepository $forms */
            $forms = $container->get(FormRepository::class);
        } else {
            /** @var DatabaseDriver $db */
            $db = $container->get(DatabaseDriver::class);
            $forms = new FormRepository($db);
        }

        $success = 0;
        $errors = [];

        foreach ($ids as $id) {
            $id = (int) $id;

            if ($id <= 0) {
                continue;
            }

            try {
                $forms->duplicate($id);
                $success++;
            } catch (\Throwable $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        if ($success > 0) {
            $app->enqueueMessage(Text::sprintf('COM_NXPEASYFORMS_FORMS_DUPLICATED', $success));
        }

        if (!empty($errors)) {
            $app->enqueueMessage(implode("\n", array_unique($errors)), $success > 0 ? 'warning' : 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_nxpeasyforms&view=forms', false));
    }
}
