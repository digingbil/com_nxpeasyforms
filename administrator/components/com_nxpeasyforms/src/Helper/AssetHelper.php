<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Helper;

use Joomla\CMS\Factory;
use Joomla\CMS\WebAsset\WebAssetManager;


use function array_values;
use function file_get_contents;
use function is_array;
use function is_file;
use function is_string;
use function json_decode;
use function ltrim;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Helper for loading compiled SPA assets from the media package.
 */
final class AssetHelper
{
    private const MANIFEST_PATH = JPATH_ROOT . '/media/com_nxpeasyforms/manifest.json';

    /**
     * Registers the assets for a given Vite entry on the current document.
     *
     * @param string $entry Manifest key, e.g. "src/admin/main.js".
     */
    public static function registerEntry(string $entry): void
    {
        $asset = self::lookup($entry);
        $document = Factory::getDocument();
        $webAsset = $document->getWebAssetManager();

        foreach ($asset['css'] as $cssFile) {
            self::registerStyle($webAsset, $cssFile);
        }

        if ($asset['file'] !== '') {
            self::registerScript($webAsset, $asset['file']);
        }
    }

    /**
     * @return array{file: string, css: array<int, string>}
     */
    private static function lookup(string $entry): array
    {
        static $manifest;

        if ($manifest === null) {
            $manifest = self::loadManifest();
        }

        if (!isset($manifest[$entry])) {
            return self::fallback($entry);
        }

        $asset = $manifest[$entry];

        return [
            'file' => isset($asset['file']) && is_string($asset['file']) ? $asset['file'] : '',
            'css' => isset($asset['css']) && is_array($asset['css']) ? array_values($asset['css']) : [],
        ];
    }

    private static function registerStyle(WebAssetManager $manager, string $file): void
    {
        if ($file === '') {
            return;
        }

        $name = 'com_nxpeasyforms.css.' . md5($file);

        if (!$manager->assetExists('style', $name)) {
            $manager->registerStyle(
                $name,
                'media/com_nxpeasyforms/' . ltrim($file, '/'),
                ['version' => 'auto', 'relative' => true]
            );
        }

        if (!$manager->isAssetActive('style', $name)) {
            $manager->useStyle($name);
        }
    }

    private static function registerScript(WebAssetManager $manager, string $file): void
    {
        if ($file === '') {
            return;
        }

        $name = 'com_nxpeasyforms.js.' . md5($file);

        if (!$manager->assetExists('script', $name)) {
            $manager->registerScript(
                $name,
                'media/com_nxpeasyforms/' . ltrim($file, '/'),
                ['version' => 'auto', 'relative' => true],
                ['defer' => true]
            );
        }

        if (!$manager->isAssetActive('script', $name)) {
            $manager->useScript($name);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadManifest(): array
    {
        if (!is_file(self::MANIFEST_PATH)) {
            return [];
        }

        $contents = file_get_contents(self::MANIFEST_PATH);

        if ($contents === false) {
            return [];
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array{file: string, css: array<int, string>}
     */
    private static function fallback(string $entry): array
    {
        if ($entry === 'src/admin/main.js') {
            return [
                'file' => 'js/admin.js',
                'css' => ['css/admin-trfcRtm1.css'],
            ];
        }

        return [
            'file' => '',
            'css' => [],
        ];
    }
}
