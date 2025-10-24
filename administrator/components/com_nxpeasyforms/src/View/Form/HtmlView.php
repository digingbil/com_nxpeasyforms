<?php

declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\View\Form;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * HTML View class for a single form (builder container).
 */
final class HtmlView extends BaseHtmlView
{
    protected $form;

    protected $item;

    protected $state;

    private string $action = '';

    private string $builderPayload = '{}';

    /**
     * {@inheritDoc}
     */
    public function display($tpl = null)
    {
        $this->form = $this->get('Form');
        $this->item = $this->get('Item');
        $this->state = $this->get('State');
        $this->action = Route::_('index.php?option=com_nxpeasyforms&task=form.save');
        $this->builderPayload = $this->encodeBuilderPayload($this->item);

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

    public function getBuilderPayload(): string
    {
        return $this->builderPayload;
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

    /**
     * @param mixed $item
     */
    private function encodeBuilderPayload($item): string
    {
        if ($item === null) {
            return '{}';
        }

        try {
            return json_encode(
                $item,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
            );
        } catch (\JsonException $exception) {
            throw new \RuntimeException(
                'COM_NXPEASYFORMS_ERROR_JSON_ENCODE_FORM_ITEM',
                0,
                $exception
            );
        }
    }
}
