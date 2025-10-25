<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Site\View\Form;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Nxpeasyforms\Site\Helper\FormRenderer;


// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Site HTML view responsible for rendering a single form.
 */
final class HtmlView extends BaseHtmlView
{
    /**
     * @var array<string, mixed>
     */
    protected $item = [];

    private string $renderedForm = '';

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

        $this->prepareAssets();

        $renderer = new FormRenderer();
        $this->renderedForm = $renderer->render($this->item);

        if ($errors = $this->get('Errors')) {
            throw new \RuntimeException(implode("\n", $errors), 500);
        }

        parent::display($tpl);
    }

    public function getRenderedForm(): string
    {
        return $this->renderedForm;
    }

    private function prepareAssets(): void
    {
        HTMLHelper::_('stylesheet', 'com_nxpeasyforms/css/frontend.css', ['version' => 'auto', 'relative' => true]);
        HTMLHelper::_('script', 'com_nxpeasyforms/js/frontend.joomla.js', ['version' => 'auto', 'relative' => true, 'defer' => true]);
        $document = $this->document ?? Factory::getDocument();
        $document->addScriptOptions('com_nxpeasyforms.frontend', [
            'restUrl' => Uri::root(true) . '/api/index.php/v1/nxpeasyforms',
            'successMessage' => Text::_('COM_NXPEASYFORMS_MESSAGE_SUBMISSION_SUCCESS'),
            'errorMessage' => Text::_('COM_NXPEASYFORMS_ERROR_VALIDATION'),
            'captchaFailedMessage' => Text::_('COM_NXPEASYFORMS_ERROR_CAPTCHA_FAILED'),
        ]);

        $document->addScriptDeclaration(
            'window.nxpEasyFormsFrontend = Joomla.getOptions("com_nxpeasyforms.frontend");'
        );
    }
}
