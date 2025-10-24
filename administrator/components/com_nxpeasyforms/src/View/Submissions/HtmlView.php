<?php

declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\View\Submissions;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * HTML View class for submissions list.
 */
final class HtmlView extends BaseHtmlView
{
    protected $items = [];

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
        ToolbarHelper::title(Text::_('COM_NXPEASYFORMS_SUBMENU_SUBMISSIONS'), 'list');

        $user = Factory::getUser();

        if ($user->authorise('nxpeasyforms.export', 'com_nxpeasyforms')) {
            ToolbarHelper::custom('submissions.export', 'download', '', Text::_('COM_NXPEASYFORMS_TOOLBAR_EXPORT'), false);
        }

        if ($user->authorise('core.delete', 'com_nxpeasyforms')) {
            ToolbarHelper::deleteList('COM_NXPEASYFORMS_CONFIRM_DELETE_SUBMISSIONS', 'submissions.delete');
        }
    }
}
