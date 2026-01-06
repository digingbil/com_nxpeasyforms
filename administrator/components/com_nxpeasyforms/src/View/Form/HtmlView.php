<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
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

use function is_array;


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

        // Hide the sidebar in form builder for better UX
        Factory::getApplication()->input->set('hidemainmenu', true);
        $this->sidebar = '';

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
     * @since 1.0.0
     */
    private function initialiseBuilder(): void
    {
        $document = $this->document ?? Factory::getDocument();
        $wa = $document->getWebAssetManager();
        AssetHelper::registerEntry('src/admin/main.js');
        HTMLHelper::_('stylesheet', 'com_nxpeasyforms/css/admin-joomla.css', ['version' => 'auto', 'relative' => true]);

        Text::script('COM_NXPEASYFORMS_FIELD_ALIAS_LABEL');
        Text::script('COM_NXPEASYFORMS_FIELD_ALIAS_PLACEHOLDER');
        Text::script('COM_NXPEASYFORMS_FIELD_ALIAS_HINT');

        $document->addScriptOptions(
            'com_nxpeasyforms.builder',
            [
                'restUrl' => Route::_('index.php?option=com_nxpeasyforms&task=ajax.route&format=json', false),
                'nonce' => Session::getFormToken(),
                'formId' => isset($this->item->id) ? (int) $this->item->id : 0,
                'builderUrl' => Route::_('index.php?option=com_nxpeasyforms&view=form'),
                'defaults' => FormDefaults::builderConfig(),
                'initialData' => $this->buildInitialData($this->item),
                'integrationsMeta' => [],
                'joomla' => [
                    'categories' => [],
                ],
                'lang' => [
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
     * Prepare initial builder payload for the current item.
     *
     * Ensures the Vue app receives decoded fields and settings so that the
     * form loads without waiting for an AJAX roundtrip.
     *
     * @param object|null $item Loaded form item.
     *
     * @return array<string,mixed>
     * @since 1.0.0
     */
    private function buildInitialData(?object $item): array
    {
        if ($item === null) {
            return [
                'id' => 0,
                'title' => '',
                'alias' => '',
                'fields' => [],
                'settings' => [],
                'active' => 1,
            ];
        }

        return [
            'id' => (int) ($item->id ?? 0),
            'title' => (string) ($item->title ?? ''),
            'alias' => (string) ($item->alias ?? ''),
            'fields' => is_array($item->fields ?? null) ? $item->fields : [],
            'settings' => is_array($item->settings ?? null) ? $item->settings : [],
            'active' => (int) ($item->active ?? 1),
            'created_at' => $item->created_at ?? null,
            'updated_at' => $item->updated_at ?? null,
        ];
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
