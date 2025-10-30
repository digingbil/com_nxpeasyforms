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
        $app = Factory::getApplication();

    $this->item = $this->get('Item');

        if (!empty($this->item['title']) && $this->document !== null) {
            $this->document->setTitle((string) $this->item['title']);
        }

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
        $document = $this->document ?? Factory::getDocument();

        // Try WebAssetManager first (Joomla 4+)
        if (method_exists($document, 'getWebAssetManager')) {
            $webAssetManager = $document->getWebAssetManager();

            if ($webAssetManager !== null) {
                $registry = method_exists($webAssetManager, 'getRegistry') ? $webAssetManager->getRegistry() : null;

                if ($registry !== null && method_exists($registry, 'addExtensionRegistryFile')) {
                    $registry->addExtensionRegistryFile('com_nxpeasyforms');
                }

                try {
                    $webAssetManager->useStyle('com_nxpeasyforms.frontend.styles');
                } catch (\InvalidArgumentException $exception) {
                    if (method_exists($webAssetManager, 'registerAndUseStyle')) {
                        $webAssetManager->registerAndUseStyle(
                            'com_nxpeasyforms.frontend.styles',
                            'media/com_nxpeasyforms/css/frontend.css',
                            ['version' => '1.0.0']
                        );
                    }
                }

                try {
                    $webAssetManager->useScript('com_nxpeasyforms.frontend.scripts');
                } catch (\InvalidArgumentException $exception) {
                    if (method_exists($webAssetManager, 'registerAndUseScript')) {
                        $webAssetManager->registerAndUseScript(
                            'com_nxpeasyforms.frontend.scripts',
                            'media/com_nxpeasyforms/js/frontend.joomla.js',
                            ['version' => '1.0.0'],
                            ['defer' => true]
                        );
                    }
                }
            }
        }

        // Fallback to direct document methods
        $mediaRoot = rtrim(Uri::root(), '/') . '/media/com_nxpeasyforms/';
        $cssUri = $mediaRoot . 'css/frontend.css';
        $jsUri = $mediaRoot . 'js/frontend.joomla.js';

        $cssPath = JPATH_ROOT . '/media/com_nxpeasyforms/css/frontend.css';
        $jsPath = JPATH_ROOT . '/media/com_nxpeasyforms/js/frontend.joomla.js';

        if (method_exists($document, 'addStyleSheet')) {
            $cssVersion = is_file($cssPath) ? (string) filemtime($cssPath) : null;
            $href = $cssUri . ($cssVersion ? ('?v=' . $cssVersion) : '');
            $document->addStyleSheet($href);
        }

        if (method_exists($document, 'addScript')) {
            $jsVersion = is_file($jsPath) ? (string) filemtime($jsPath) : null;
            $src = $jsUri . ($jsVersion ? ('?v=' . $jsVersion) : '');
            $document->addScript($src, [], ['defer' => true]);
        }

        if (method_exists($document, 'addScriptOptions')) {
            $document->addScriptOptions('com_nxpeasyforms.frontend', [
                'restUrl' => Uri::root(true) . '/api/index.php/v1/nxpeasyforms',
                'successMessage' => Text::_('COM_NXPEASYFORMS_MESSAGE_SUBMISSION_SUCCESS'),
                'errorMessage' => Text::_('COM_NXPEASYFORMS_ERROR_VALIDATION'),
                'captchaFailedMessage' => Text::_('COM_NXPEASYFORMS_ERROR_CAPTCHA_FAILED'),
            ]);
        }

        if (method_exists($document, 'addScriptDeclaration')) {
            $document->addScriptDeclaration(
                'window.nxpEasyFormsFrontend = window.nxpEasyFormsFrontend || Joomla.getOptions("com_nxpeasyforms.frontend");'
            );
        }
    }
}
