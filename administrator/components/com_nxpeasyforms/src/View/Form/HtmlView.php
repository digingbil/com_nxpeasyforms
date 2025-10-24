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
use Joomla\Component\Nxpeasyforms\Administrator\Helper\AssetHelper;
use Joomla\Component\Nxpeasyforms\Administrator\Helper\FormDefaults;

/**
 * HTML View class for a single form (builder container).
 */
final class HtmlView extends BaseHtmlView
{
    protected $form;

    protected $item;

    protected $state;

    private string $action = '';

    /**
     * {@inheritDoc}
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

    public function getAction(): string
    {
        return $this->action;
    }

    private function initialiseBuilder(): void
    {
        $document = $this->document ?? Factory::getDocument();
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
                'integrationsMeta' => [],
                'wpData' => [
                    'postTypes' => [],
                    'postStatuses' => [],
                    'taxonomies' => [],
                ],
                'woo' => [
                    'active' => false,
                ],
                'i18n' => [
                    'formSaved' => Text::_('COM_NXPEASYFORMS_FORM_SAVED'),
                    'formCreated' => Text::_('COM_NXPEASYFORMS_FORM_CREATED'),
                    'saving' => Text::_('COM_NXPEASYFORMS_FORM_SAVING'),
                    'defaultTitle' => Text::_('COM_NXPEASYFORMS_UNTITLED_FORM'),
                ],
            ]
        );

        $document->addScriptDeclaration(
            'window.nxpEasyForms = window.nxpEasyForms || {};'
            . 'window.nxpEasyForms.builder = Joomla.getOptions("com_nxpeasyforms.builder");'
        );
    }

    private function addToolbar(): void
    {
        ToolbarHelper::title(
            $this->item && isset($this->item->id) && $this->item->id > 0
                ? Text::_('COM_NXPEASYFORMS_TOOLBAR_EDIT_FORM')
                : Text::_('COM_NXPEASYFORMS_TOOLBAR_NEW_FORM'),
            'pencil-2'
        );

        ToolbarHelper::apply('form.apply');
        ToolbarHelper::save('form.save');
        ToolbarHelper::save2new('form.save2new');
        ToolbarHelper::cancel('form.cancel');
    }
}
