<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\View\Submissions;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;


// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * HTML View class for submissions list.
 *
 * Renders the submissions list view with filtering, sorting and pagination
 * for viewing and managing form submissions in the administrator interface.
 *
 * @since 1.0.0
 */
final class HtmlView extends BaseHtmlView
{
    protected $items = [];

    protected $pagination;

    protected $state;

    protected $filterForm;

    protected $activeFilters;

	/**
	 * Render the view.
	 *
	 * Prepares filtered, sorted submissions list data for display in the admin interface.
	 *
	 * @param   string|null  $tpl  The layout template name (optional).
	 *
	 * @return void
	 *
	 * @throws \RuntimeException When an error is encountered loading view data.
	 * @throws \Exception
	 * @since 1.0.0
	 */
    public function display($tpl = null): void {
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

    /**
     * Add toolbar buttons and title for the submissions list view.
     *
     * @return void
     * @since 1.0.0
     */
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
