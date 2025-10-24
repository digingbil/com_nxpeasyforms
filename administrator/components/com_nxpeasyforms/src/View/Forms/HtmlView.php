<?php

declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\View\Forms;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * HTML View class for listing forms.
 *
 * @psalm-type FormListItem = object{
 *     id:int,
 *     title:string,
 *     active:int,
 *     created_at:string|null,
 *     updated_at:string|null
 * }
 */
final class HtmlView extends BaseHtmlView
{
    /**
     * @var array<int,FormListItem>
     */
    protected array $items = [];

    protected $pagination;

    protected $state;

    protected $filterForm;

    protected $activeFilters;

    /**
     * {@inheritDoc}
     */
    public function display($tpl = null)
    {
        $this->state = $this->get('State');
        $this->items = $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->filterForm = $this->get('FilterForm');
        $this->activeFilters = $this->get('ActiveFilters');

        if ($errors = $this->get('Errors')) {
            throw new \RuntimeException(implode("\n", $errors), 500);
        }

        $this->addToolbar();

        parent::display($tpl);
    }

    private function addToolbar(): void
    {
        ToolbarHelper::title(Text::_('COM_NXPEASYFORMS_SUBMENU_FORMS'), 'pencil-2');

        $user = Factory::getUser();

        if ($user->authorise('core.create', 'com_nxpeasyforms')) {
            ToolbarHelper::addNew('form.add');
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
