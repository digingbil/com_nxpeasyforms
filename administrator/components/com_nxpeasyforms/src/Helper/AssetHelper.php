<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Helper;

use Joomla\CMS\Factory;
use Joomla\CMS\WebAsset\WebAssetManager;


use function array_values;
use function basename;
use function file_get_contents;
use function filemtime;
use function is_array;
use function is_file;
use function is_string;
use function json_decode;
use function ltrim;
use function glob;
use function usort;
use function preg_match;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Helper for loading compiled SPA assets from the media package.
 * @since 1.0.0
 */
final class AssetHelper
{
    private const MANIFEST_PATH = JPATH_ROOT . '/media/com_nxpeasyforms/manifest.json';

    /**
     * Registers the assets for a given Vite entry on the current document.
     *
     * @param string $entry Manifest key, e.g. "src/admin/main.js".
     *
     * @since 1.0.0
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
     * Look up the compiled asset for a given entry in the manifest.
     *
     * @return array{file: string, css: array<int, string>}
     * @since 1.0.0
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

	/**
	 * Register a style asset.
	 *
	 * @param   WebAssetManager  $manager
	 * @param   string           $file
	 *
	 *
	 * @since version
	 */
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

	/**
	 * Register a script asset.
	 *
	 * @param   WebAssetManager  $manager
	 * @param   string           $file
	 * @since 1.00
	 */
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
     * Loads a manifest.json file.
     *
     * @return array<string, mixed>
     * @since 1.0.0
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
     * Fallback for when the manifest is missing or invalid.
     *
     * @return array{file: string, css: array<int, string>}
     * @since 1.0.0
     */
    private static function fallback(string $entry): array
    {
        if ($entry === 'src/admin/main.js') {
        $cssFiles = self::latestMatchingFiles('/css/admin-*.css', 'css/admin.css');
            return [
                'file' => 'js/admin.js',
                'css' => $cssFiles,
            ];
        }

        return [
            'file' => '',
            'css' => [],
        ];
    }

    /**
     * Get the latest matching files from the given pattern.
     *
     * @return array<int, string>
     * @since 1.0.0
     */
    private static function latestMatchingFiles(string $pattern, string $fallback): array
    {
        $fallbackPath = JPATH_ROOT . '/media/com_nxpeasyforms/' . $fallback;

        if (is_file($fallbackPath)) {
            return [$fallback];
        }

        $fullPattern = JPATH_ROOT . '/media/com_nxpeasyforms' . $pattern;

        $matches = glob($fullPattern, GLOB_NOSORT) ?: [];

        $matches = array_filter(
            $matches,
            static function (string $path): bool {
                $filename = basename($path);

                // Keep only files like admin-<hash>.css where hash is alphanumeric.
                return (bool) preg_match('/^admin-[A-Za-z0-9]+\\.css$/', $filename);
            }
        );

        if ($matches === []) {
            return [];
        }

        usort(
            $matches,
            static function (string $a, string $b): int {
                return (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0);
            }
        );

        $latest = $matches[0];

        return ['css/' . basename($latest)];
    }
}
