<?php
declare(strict_types=1);

namespace Joomla\Plugin\Content\Nxpeasyforms;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
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
}
