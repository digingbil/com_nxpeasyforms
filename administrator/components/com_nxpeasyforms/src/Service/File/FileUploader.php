<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Service\File;

use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Validation\FileValidator;


use function bin2hex;
use function is_string;
use function random_bytes;
use function rtrim;
use function strtolower;
use function trim;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Handles secure storage of uploaded files in the Joomla images directory.
 * @since 1.0.0
 */
final class FileUploader
{
    private FileValidator $validator;

    private string $basePath;

    private string $baseUrl;

    public function __construct(?FileValidator $validator = null, ?string $basePath = null, ?string $baseUrl = null)
    {
        $this->validator = $validator ?? new FileValidator();

        $defaultPath = rtrim(JPATH_ROOT . '/images/nxpeasyforms', '/\\');
        $defaultUrl = rtrim(Uri::root() . 'images/nxpeasyforms', '/');

        $this->basePath = $basePath !== null ? rtrim($basePath, '/\\') : $defaultPath;
        $this->baseUrl = $baseUrl !== null ? rtrim($baseUrl, '/') : $defaultUrl;
    }

	/**
	 * Handles file upload and returns upload result details
	 *
	 * @param   array<string, mixed>  $field  Field configuration array
	 * @param   array<string, mixed>  $files  Files array from request
	 *
	 * @return array{0: string, 1: array<string, mixed>, 2: ?string} Array containing:
	 *         - [0] Relative file path
	 *         - [1] File details array with path, url, type, name and size
	 *         - [2] Error message or null on success
	 * @since 1.0.0
	 */
	public function handle(array $field, array $files): array
    {
        $name = $field['name'] ?? '';

        if ($name === '') {
            return ['', [], null];
        }

        $file = $this->validator->extractUploadedFile($name, $files);

        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['', [], null];
        }

        if ((int) ($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
            return ['', [], Text::_('COM_NXPEASYFORMS_ERROR_FILE_UPLOAD_FAILED')];
        }

        $originalName = isset($file['name']) && is_string($file['name']) ? File::makeSafe($file['name']) : '';
        $mime = $this->validator->detectMimeType($file['tmp_name']);

        if ($mime === null) {
            return ['', [], Text::_('COM_NXPEASYFORMS_ERROR_FILE_TYPE_NOT_ALLOWED')];
        }

        $extension = strtolower(FileValidator::getPreferredExtension($mime) ?? File::getExt($originalName));

        try {
            $this->ensureStorageDirectory();
        } catch (\RuntimeException $exception) {
            return ['', [], Text::_('COM_NXPEASYFORMS_ERROR_FILE_DIRECTORY')];
        }

        $filename = $this->generateFilename($extension);
        $targetPath = $this->basePath . '/' . $filename;

        if (!File::upload($file['tmp_name'], $targetPath, false, true)) {
            return ['', [], Text::_('COM_NXPEASYFORMS_ERROR_FILE_MOVE_FAILED')];
        }

        $relative = 'images/nxpeasyforms/' . $filename;

        return [
            $relative,
            [
                'path' => $relative,
                'url' => $this->baseUrl . '/' . $filename,
                'type' => $mime,
                'original_name' => $originalName,
                'size' => $file['size'] ?? null,
            ],
            null,
        ];
    }

	/**
	 * Ensures that the storage directory exists and is accessible.
	 * If the directory does not exist, it attempts to create it.
	 * Also creates security files (.htaccess, index.html, web.config) to prevent
	 * direct access to uploaded files and PHP execution.
	 * Throws a RuntimeException if the directory creation fails.
	 *
	 * @return void
	 * @since 1.0.0
	 */
    private function ensureStorageDirectory(): void
    {
        $directoryCreated = false;

        if (!is_dir($this->basePath)) {
            if (!Folder::create($this->basePath)) {
                throw new \RuntimeException(Text::_('COM_NXPEASYFORMS_ERROR_FILE_DIRECTORY'));
            }
            $directoryCreated = true;
        }

        // Create security files if they don't exist
        $this->ensureSecurityFiles($directoryCreated);
    }

    /**
     * Creates security files in the upload directory to prevent PHP execution
     * and directory listing.
     *
     * @param bool $forceCreate Force creation even if files exist.
     *
     * @return void
     * @since 1.0.6
     */
    private function ensureSecurityFiles(bool $forceCreate = false): void
    {
        // Create .htaccess for Apache
        $htaccessPath = $this->basePath . '/.htaccess';
        if ($forceCreate || !is_file($htaccessPath)) {
            $htaccessContent = <<<'HTACCESS'
# Prevent PHP execution in upload directory
<FilesMatch "\.(php|phtml|phar|php[3-8]|phps|pht|shtml|cgi|pl|py|exe|sh|bat)$">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order allow,deny
        Deny from all
    </IfModule>
</FilesMatch>

# Disable script execution
<IfModule mod_php.c>
    php_flag engine off
</IfModule>
<IfModule mod_php7.c>
    php_flag engine off
</IfModule>
<IfModule mod_php8.c>
    php_flag engine off
</IfModule>

# Prevent directory listing
Options -Indexes

# Force download for unknown types and prevent MIME sniffing
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
</IfModule>
HTACCESS;
            @file_put_contents($htaccessPath, $htaccessContent);
        }

        // Create index.html to prevent directory listing fallback
        $indexPath = $this->basePath . '/index.html';
        if ($forceCreate || !is_file($indexPath)) {
            $indexContent = '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body></body></html>';
            @file_put_contents($indexPath, $indexContent);
        }

        // Create web.config for IIS
        $webConfigPath = $this->basePath . '/web.config';
        if ($forceCreate || !is_file($webConfigPath)) {
            $webConfigContent = <<<'WEBCONFIG'
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <system.webServer>
        <handlers>
            <remove name="PHP-FastCGI" />
            <remove name="PHP" />
            <remove name="CGI-exe" />
        </handlers>
        <security>
            <requestFiltering>
                <fileExtensions>
                    <add fileExtension=".php" allowed="false" />
                    <add fileExtension=".phtml" allowed="false" />
                    <add fileExtension=".phar" allowed="false" />
                </fileExtensions>
            </requestFiltering>
        </security>
    </system.webServer>
</configuration>
WEBCONFIG;
            @file_put_contents($webConfigPath, $webConfigContent);
        }
    }

	/**
	 * Generates a random filename with the provided extension.
	 * If no extension is given, the filename will consist only of a random seed.
	 *
	 * @param   string  $extension  The file extension to append to the generated filename. If empty, no extension will be added.
	 *
	 * @return string The generated filename, optionally including the specified extension.
	 * @since 1.0.0
	 */
    private function generateFilename(string $extension): string
    {
        $seed = bin2hex(random_bytes(16));

        if ($extension === '') {
            return $seed;
        }

        $extension = trim($extension, '.');

        return $seed . '.' . $extension;
    }
}
