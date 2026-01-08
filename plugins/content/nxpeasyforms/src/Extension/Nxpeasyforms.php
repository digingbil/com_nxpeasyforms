<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
declare(strict_types=1);

namespace Joomla\Plugin\Content\Nxpeasyforms\Extension;

use Joomla\CMS\Access\Access;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Repository\FormRepository;
use Joomla\Component\Nxpeasyforms\Site\Helper\FormRenderer;
use Joomla\Database\DatabaseDriver;

use function class_exists;
use function is_array;
use function is_object;
use function is_numeric;
use function is_string;
use function preg_match;
use function preg_replace_callback;
use function property_exists;
use function sprintf;
use function stripos;
use function trim;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Content plugin to render NXP Easy Forms via {nxpeasyform 123} shortcode.
 */
class Nxpeasyforms extends CMSPlugin
{
    protected $autoloadLanguage = true;

    private bool $assetsLoaded = false;

    private ?FormRenderer $renderer = null;

    private bool $namespacesRegistered = false;

    private bool $shouldAttachAssets = false;

    /**
     * Cached ACL rules by asset name.
     *
     * @var array<string, array<string, int>|null>
     */
    private array $assetRulesCache = [];

    /**
     * Render shortcodes found in Joomla content on the site application.
     *
     * @param mixed $article Content item or raw string reference.
     * @param mixed $params  Content parameters.
     */
    public function onContentPrepare($context, &$article, &$params, $page = 0): void
    {
        $app = Factory::getApplication();

        if (!$app->isClient('site')) {
            return;
        }

        // Load frontend language file for user-facing messages
        $language = $app->getLanguage();
        $language->load('com_nxpeasyforms', JPATH_SITE);

        $text = null;
        $articleIsObject = is_object($article);

        if ($articleIsObject) {
            if (!property_exists($article, 'text')) {
                return;
            }

            $text = is_string($article->text) ? $article->text : (string) $article->text;
        } elseif (is_string($article)) {
            $text = $article;
        } else {
            return;
        }

        if (stripos($text, '{nxpeasyform') === false) {
            return;
        }

        $rendered = $this->renderShortcodes($text, $app);

        if ($articleIsObject) {
            $article->text = $rendered;
        } else {
            $article = $rendered;
        }
    }

    /**
     * Replace every shortcode occurrence with the rendered form markup.
     */
    private function renderShortcodes(string $text, CMSApplicationInterface $app): string
    {
        $pattern = '/{nxpeasyform\s+(?<arguments>[^}]+)}/i';

        $result = preg_replace_callback(
            $pattern,
            function (array $match) use ($app): string {
                $arguments = trim($match['arguments'] ?? '');
                $formId = $this->extractFormId($arguments);

                if ($formId <= 0) {
                    return '';
                }

                $user = $app->getIdentity();

                $this->ensureAutoloaders();

                if (!$this->canViewForm((int) $user->id, $formId)) {
                    return '';
                }

                try {
                    $form = $this->loadForm($formId);

                    if (empty($form) || (int) ($form['active'] ?? 1) !== 1) {
                        return '';
                    }

                    $this->markAssetsRequired();

                    return $this->getRenderer()->render($form);
                } catch (\Throwable $exception) {
                    return '';
                }
            },
            $text
        );

        return is_string($result) ? $result : $text;
    }

    public function onBeforeCompileHead(): void
    {
        if (!$this->shouldAttachAssets) {
            return;
        }

        $this->prepareAssets();
    }

    private function canViewForm(int $userId, int $formId): bool
    {
        $groups = Access::getGroupsByUser($userId);

        $formDecision = $this->evaluatePermission(sprintf('com_nxpeasyforms.form.%d', $formId), $groups);

        if ($formDecision === false) {
            return false;
        }

        if ($formDecision === true) {
            return true;
        }

        $componentDecision = $this->evaluatePermission('com_nxpeasyforms', $groups);

        if ($componentDecision === false) {
            return false;
        }

        if ($componentDecision === true) {
            return true;
        }

        return true;
    }

    /**
     * @param array<int> $groupIds
     */
    private function evaluatePermission(string $assetName, array $groupIds): ?bool
    {
        $rules = $this->getAssetViewRules($assetName);

        if ($rules === null) {
            return null;
        }

        foreach ($groupIds as $groupId) {
            $key = (string) $groupId;

            if (array_key_exists($key, $rules) && (int) $rules[$key] === 0) {
                return false;
            }
        }

        foreach ($groupIds as $groupId) {
            $key = (string) $groupId;

            if (array_key_exists($key, $rules) && (int) $rules[$key] === 1) {
                return true;
            }
        }

        return null;
    }

    /**
     * @return array<string, int>|null
     */
    private function getAssetViewRules(string $assetName): ?array
    {
        if (array_key_exists($assetName, $this->assetRulesCache)) {
            return $this->assetRulesCache[$assetName];
        }

        $asset = Table::getInstance('Asset');

        if (!$asset->load(['name' => $assetName])) {
            $this->assetRulesCache[$assetName] = null;

            return null;
        }

        $payload = json_decode((string) $asset->rules, true);

        if (!is_array($payload)) {
            $this->assetRulesCache[$assetName] = null;

            return null;
        }

        foreach ($payload as $action => $assignments) {
            if (!is_array($assignments)) {
                continue;
            }

            if ($action === 'core.view' || str_ends_with($action, 'core.view')) {
                $rules = [];

                foreach ($assignments as $groupId => $value) {
                    $rules[(string) $groupId] = (int) $value;
                }

                $this->assetRulesCache[$assetName] = $rules;

                return $rules;
            }
        }

        $this->assetRulesCache[$assetName] = null;

        return null;
    }

    private function ensureAutoloaders(): void
    {
        if ($this->namespacesRegistered) {
            return;
        }

        if (!class_exists(FormRenderer::class)) {
            \JLoader::registerNamespace(
                'Joomla\\Component\\Nxpeasyforms\\Site',
                JPATH_SITE . '/components/com_nxpeasyforms/src'
            );
        }

        if (!class_exists(FormRepository::class)) {
            \JLoader::registerNamespace(
                'Joomla\\Component\\Nxpeasyforms\\Administrator',
                JPATH_ADMINISTRATOR . '/components/com_nxpeasyforms/src'
            );
        }
        $this->namespacesRegistered = true;
    }

    private function markAssetsRequired(): void
    {
        $this->shouldAttachAssets = true;
    }

    /**
     * Retrieve the singleton form renderer instance.
     */
    private function getRenderer(): FormRenderer
    {
        if ($this->renderer === null) {
            $this->renderer = new FormRenderer();
        }

        return $this->renderer;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadForm(int $formId): array
    {
        $this->ensureAutoloaders();

        try {
            Factory::getApplication()->bootComponent('com_nxpeasyforms');
        } catch (\Throwable $exception) {
            return [];
        }

        $container = Factory::getContainer();

        if (method_exists($container, 'has') && $container->has(FormRepository::class)) {
            /** @var FormRepository $repository */
            $repository = $container->get(FormRepository::class);
        } else {
            /** @var DatabaseDriver $db */
            $db = $container->get(DatabaseDriver::class);
            $repository = new FormRepository($db);
        }

        $item = $repository->find($formId);

        return is_array($item) ? $item : [];
    }

    private function extractFormId(string $arguments): int
    {
        if ($arguments === '') {
            return 0;
        }

        if (is_numeric($arguments)) {
            return (int) $arguments;
        }

        $pattern = '/\bid\s*=\s*(?:["\']?(?<id>\d+)["\']?)/i';

        if (preg_match($pattern, $arguments, $matches)) {
            return (int) ($matches['id'] ?? 0);
        }

        return 0;
    }

    private function prepareAssets(): void
    {
        if ($this->assetsLoaded) {
            return;
        }

        $this->assetsLoaded = true;
        $this->shouldAttachAssets = false;

        try {
            $document = Factory::getDocument();
        } catch (\Throwable $exception) {
            return;
        }

        if (!is_object($document)) {
            return;
        }

        $wamLoaded = false;

        if (method_exists($document, 'getWebAssetManager')) {
            $webAssetManager = $document->getWebAssetManager();

            if ($webAssetManager !== null) {
                try {
                    // registerAndUseStyle/Script are magic methods via __call()
                    // Use 'media/...' path with 'relative' => true
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
                    $wamLoaded = true;
                } catch (\Throwable $exception) {
                    // WAM failed, will fall back
                }
            }
        }

        // Fallback to direct document methods only if WebAssetManager is not available
        if (!$wamLoaded) {
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
            $document->addScriptDeclaration('window.nxpEasyFormsFrontend = window.nxpEasyFormsFrontend || Joomla.getOptions("com_nxpeasyforms.frontend");');
        }
    }
}
