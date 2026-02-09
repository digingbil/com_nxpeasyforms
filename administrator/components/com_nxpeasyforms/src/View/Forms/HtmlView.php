<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\View\Forms;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;


// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * HTML View class for listing forms.
 *
 * Renders the forms list view with filtering, sorting and pagination
 * for managing form definitions in the administrator interface.
 *
 * @psalm-type FormListItem = object{
 *     id:int,
 *     title:string,
 *     active:int,
 *     created_at:string|null,
 *     updated_at:string|null
 * }
 *
 * @since 1.0.0
 */
final class HtmlView extends BaseHtmlView
{
    /**
     * @var array<int,FormListItem>
     */
    public $items = [];

    public $pagination;

    public $state;

    public $filterForm;

    public $activeFilters;

    private bool $modal = false;

    /**
     * Render the view.
     *
     * Prepares filtered, sorted forms list data for display in the admin interface.
     *
     * @param string|null $tpl The layout template name (optional).
     *
     * @return void
     *
     * @throws \RuntimeException When an error is encountered loading view data.
     * @since 1.0.0
     */
    public function display($tpl = null)
    {
        $this->state = $this->get('State');
        $items = $this->get('Items');
        $this->items = is_array($items) ? $items : [];
        $this->pagination = $this->get('Pagination');
        $this->filterForm = $this->get('FilterForm');
        $this->activeFilters = $this->get('ActiveFilters');
        $this->modal = Factory::getApplication()->input->getCmd('layout') === 'modal';

        if ($errors = $this->get('Errors')) {
            throw new \RuntimeException(implode("\n", $errors), 500);
        }

        if ($this->modal) {
            $document = $this->document ?? Factory::getDocument();
            $document->setTitle(Text::_('COM_NXPEASYFORMS_SELECT_FORM'));
        } else {
            $this->addToolbar();
        }

        parent::display($tpl);
    }

    /**
     * Indicates if the view is rendered within a modal.
     *
     * @return bool
     * @since 1.0.1
     */
    public function isModal(): bool
    {
        return $this->modal;
    }

    /**
     * Add toolbar buttons and title for the forms list view.
     *
     * @return void
     * @since 1.0.0
     */
    private function addToolbar(): void
    {
        ToolbarHelper::title(Text::_('COM_NXPEASYFORMS_SUBMENU_FORMS'), 'pencil-2');

        $user = Factory::getApplication()->getIdentity();

        if ($user->authorise('core.create', 'com_nxpeasyforms')) {
            ToolbarHelper::addNew('form.add');
            ToolbarHelper::custom('forms.duplicate', 'copy', 'copy', 'JTOOLBAR_DUPLICATE', true);
        }

        if ($user->authorise('core.edit', 'com_nxpeasyforms')) {
            ToolbarHelper::editList('form.edit');
        }

        if ($user->authorise('core.delete', 'com_nxpeasyforms')) {
            ToolbarHelper::deleteList('COM_NXPEASYFORMS_CONFIRM_DELETE_FORMS', 'forms.delete');
        }

        if ($user->authorise('core.admin', 'com_nxpeasyforms')) {
            ToolbarHelper::preferences('com_nxpeasyforms');
        }
    }
}
