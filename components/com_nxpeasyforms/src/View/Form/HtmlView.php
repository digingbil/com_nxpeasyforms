<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */
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
        $assetsLoaded = false;

        // Try WebAssetManager first (Joomla 4+)
        if (method_exists($document, 'getWebAssetManager')) {
            $webAssetManager = $document->getWebAssetManager();

            if ($webAssetManager !== null) {
                try {
                    // registerAndUseStyle/Script are magic methods via __call()
                    // Use same format as nxpeasycart: 'media/...' with 'relative' => true
                    $webAssetManager->registerAndUseStyle(
                        'com_nxpeasyforms.frontend.styles',
                        'media/com_nxpeasyforms/css/frontend.css',
                        ['version' => 'auto', 'relative' => true]
                    );
                    $webAssetManager->registerAndUseScript(
                        'com_nxpeasyforms.frontend.scripts',
                        'media/com_nxpeasyforms/js/frontend.joomla.js',
                        ['version' => 'auto', 'relative' => true],
                        ['defer' => true],
                        ['core']
                    );
                    $assetsLoaded = true;
                } catch (\Throwable $exception) {
                    // WAM failed, will fall back
                }
            }
        }

        // Fallback to direct document methods only if WebAssetManager failed
        if (!$assetsLoaded) {
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
        }

        // Script options are always needed regardless of loading method
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
