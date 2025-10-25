<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\View\Form;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Session\Session;
use Joomla\CMS\WebAsset\WebAssetManager;
use Joomla\Component\Nxpeasyforms\Administrator\Helper\AssetHelper;
use Joomla\Component\Nxpeasyforms\Administrator\Helper\FormDefaults;


// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * HTML View class for a single form (builder container).
 *
 * Renders the form builder interface for creating and editing form definitions.
 * Loads the Vue.js SPA assets and prepares the builder configuration.
 *
 * @since 1.0.0
 */
final class HtmlView extends BaseHtmlView
{
    protected $form;

    protected $item;

    protected $state;

    private string $action = '';

    /**
     * Render the view.
     *
     * Prepares form data and builder assets for display in the admin interface.
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
        $this->form = $this->get('Form');
        $this->item = $this->get('Item');
        $this->state = $this->get('State');
        $this->action = Route::_('index.php?option=com_nxpeasyforms&task=form.save');
        $this->initialiseBuilder();

        if ($errors = $this->get('Errors')) {
            throw new \RuntimeException(implode("\n", $errors), 500);
        }

        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * Get the form action URL for form submission.
     *
     * @return string The form action URL.
     * @since 1.0.0
     */
    public function getAction(): string
    {
        return $this->action;
    }

	/**
	 * Initialise the form builder Vue.js application.
	 *
	 * Loads SPA assets and prepares the configuration object
	 * for the builder interface.
	 *
	 * @return void
	 * @throws \Exception
	 * @since 1.0.0
	 */
    private function initialiseBuilder(): void
    {
        $document = $this->getDocument() ?? Factory::getApplication()->getDocument();
        $wa = $document->getWebAssetManager();
        AssetHelper::registerEntry('src/admin/main.js');
        HTMLHelper::_('stylesheet', 'com_nxpeasyforms/css/admin-joomla.css', ['version' => 'auto', 'relative' => true]);

        $document->addScriptOptions(
            'com_nxpeasyforms.builder',
            [
                'restUrl' => Route::_('index.php?option=com_nxpeasyforms&task=ajax.route&format=json', false),
                'nonce' => Session::getFormToken(),
                'formId' => isset($this->item->id) ? (int) $this->item->id : 0,
                'builderUrl' => Route::_('index.php?option=com_nxpeasyforms&view=form'),
                'defaults' => FormDefaults::builderConfig(),
                'initialData' => [
                    'title' => $this->item->title ?? Text::_('COM_NXPEASYFORMS_UNTITLED_FORM'),
                    'fields' => is_array($this->item->fields ?? null) ? $this->item->fields : [],
                    'settings' => is_array($this->item->settings ?? null) ? $this->item->settings : [],
                ],
                'integrationsMeta' => [],
                'joomla' => [
                    'categories' => [],
                ],
                'i18n' => [
                    'formSaved' => Text::_('COM_NXPEASYFORMS_FORM_SAVED'),
                    'formCreated' => Text::_('COM_NXPEASYFORMS_FORM_CREATED'),
                    'saving' => Text::_('COM_NXPEASYFORMS_FORM_SAVING'),
                    'defaultTitle' => Text::_('COM_NXPEASYFORMS_UNTITLED_FORM'),
                ],
            ]
        );

        $wa->addInlineScript(
            'window.nxpEasyForms = window.nxpEasyForms || {};'
            . 'window.nxpEasyForms.builder = Joomla.getOptions("com_nxpeasyforms.builder");'
        );
    }

    /**
     * Add toolbar buttons and title for the form builder view.
     *
     * @return void
     * @since 1.0.0
     */
    private function addToolbar(): void
    {
        ToolbarHelper::title(
            $this->item && isset($this->item->id) && $this->item->id > 0
                ? Text::_('COM_NXPEASYFORMS_TOOLBAR_EDIT_FORM')
                : Text::_('COM_NXPEASYFORMS_TOOLBAR_NEW_FORM'),
            'pencil-2'
        );

        ToolbarHelper::cancel('form.cancel');
    }
}
