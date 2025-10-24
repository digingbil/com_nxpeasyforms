<?php

declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Site\View\Form;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

/**
 * Site HTML view responsible for rendering a single form.
 */
final class HtmlView extends BaseHtmlView
{
    /**
     * @var array<string, mixed>
     */
    protected array $item = [];

    /**
     * Returns the decoded form payload for the layout.
     *
     * @return array<string, mixed>
     */
    public function getFormData(): array
    {
        return $this->item;
    }

    /**
     * {@inheritDoc}
     */
    public function display($tpl = null)
    {
        $this->item = $this->get('Item');

        if ($errors = $this->get('Errors')) {
            throw new \RuntimeException(implode("\n", $errors), 500);
        }

        parent::display($tpl);
    }
}
