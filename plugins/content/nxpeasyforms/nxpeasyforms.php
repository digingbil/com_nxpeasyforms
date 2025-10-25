<?php
declare(strict_types=1);

namespace Joomla\Plugin\Content\Nxpeasyforms;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Nxpeasyforms\Site\Helper\FormRenderer;


use function preg_match_all;
use function preg_replace;
use function sprintf;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Content plugin to render NXP Easy Forms via {nxpeasyform 123} shortcode.
 */
final class Nxpeasyforms extends CMSPlugin
{
    protected $autoloadLanguage = true;

    private bool $assetsLoaded = false;

    public function onContentPrepare($context, &$article, &$params, $page = 0): void
    {
        if (!isset($article->text) || stripos($article->text, '{nxpeasyform') === false) {
            return;
        }

        $pattern = '/{nxpeasyform\s+(?<id>\d+)}/i';
        $article->text = preg_replace($pattern, function (array $match) {
            $formId = (int) ($match['id'] ?? 0);

            if ($formId <= 0) {
                return '';
            }

            try {
                $form = $this->loadForm($formId);
            } catch (\Throwable $exception) {
                return Text::_('COM_NXPEASYFORMS_ERROR_FORM_NOT_FOUND');
            }

            if (empty($form) || (int) ($form['active'] ?? 1) !== 1) {
                return Text::_('COM_NXPEASYFORMS_ERROR_FORM_NOT_FOUND');
            }

            $this->prepareAssets();

            $renderer = new FormRenderer();

            return $renderer->render($form);
        }, $article->text);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadForm(int $formId): array
    {
        $app = Factory::getApplication();
        $component = $app->bootComponent('com_nxpeasyforms');
        $factory = $component->getMVCFactory();

        /** @var \Joomla\Component\Nxpeasyforms\Site\Model\FormModel $model */
        $model = $factory->createModel('Form', 'Site', ['ignore_request' => true]);
        $model->setState('form.id', $formId);

        $item = $model->getItem($formId);

        return is_array($item) ? $item : [];
    }

    private function prepareAssets(): void
    {
        if ($this->assetsLoaded) {
            return;
        }

        $this->assetsLoaded = true;

        HTMLHelper::_('stylesheet', 'com_nxpeasyforms/css/frontend.css', ['version' => 'auto', 'relative' => true]);
        HTMLHelper::_('script', 'com_nxpeasyforms/js/frontend.joomla.js', ['version' => 'auto', 'relative' => true, 'defer' => true]);

        try {
            $document = Factory::getDocument();
        } catch (\Throwable $exception) {
            return;
        }

        if (!is_object($document)) {
            return;
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
            $document->addScriptDeclaration('window.nxpEasyFormsFrontend = window.nxpEasyFormsFrontend || Joomla.getOptions("com_nxpeasyforms.frontend");');
        }
    }
}
