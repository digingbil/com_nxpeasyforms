<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Service\Validation;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\Event;


use function array_map;
use function explode;
use function finfo_close;
use function finfo_file;
use function finfo_open;
use function function_exists;
use function in_array;
use function is_array;
use function is_file;
use function is_string;
use function mime_content_type;
use function pathinfo;
use function str_starts_with;
use function strpos;
use function strtolower;
use function trim;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Performs security and integrity checks for uploaded files.
 * Validates file types, sizes, extensions, and image dimensions to ensure secure file uploads.
 * Implements multiple validation methods including mime type detection, extension matching,
 * and specific image validation for maximum security.
 * @since 1.0.0
 */
final class FileValidator
{
    /**
     * List of dangerous file extensions that should never be allowed.
     * These extensions can lead to code execution if uploaded.
     *
     * @var array<int, string>
     * @since 1.0.6
     */
    private const DANGEROUS_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8',
        'phtml', 'phar', 'pht', 'phps',
        'exe', 'com', 'bat', 'cmd', 'sh', 'bash', 'zsh',
        'pl', 'py', 'rb', 'cgi',
        'asp', 'aspx', 'jsp', 'jspx',
        'htaccess', 'htpasswd',
        'shtml', 'shtm',
        'svg',  // Can contain JavaScript
    ];

    /**
     * List of MIME types that should never be allowed even if plugins try to add them.
     *
     * @var array<int, string>
     * @since 1.0.6
     */
    private const FORBIDDEN_MIME_TYPES = [
        'application/x-php',
        'application/x-httpd-php',
        'application/x-executable',
        'application/x-msdownload',
        'application/x-msdos-program',
        'text/x-php',
        'text/x-python',
        'text/x-perl',
        'text/x-shellscript',
        'image/svg+xml',  // Can contain JavaScript
    ];

    private const ALLOWED_FILE_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
        'text/csv',
    ];

	/**
	 * Associates MIME types with their allowed file extensions for security validation.
	 * Each MIME type maps to one or more accepted extensions, with the first extension being the preferred one.
	 *
	 * @var array<string, array<int, string>>
	 * @since 1.0.0
	 */
	private const MIME_EXTENSION_MAP = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/gif' => ['gif'],
        'image/webp' => ['webp'],
        'application/pdf' => ['pdf'],
        'application/msword' => ['doc'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
        'application/vnd.ms-excel' => ['xls'],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
        'text/plain' => ['txt'],
        'text/csv' => ['csv'],
    ];

    private ?DispatcherInterface $dispatcher;

    public function __construct(?DispatcherInterface $dispatcher = null)
    {
        $this->dispatcher = $dispatcher;

        if ($this->dispatcher === null) {
            try {
                $this->dispatcher = Factory::getApplication()->getDispatcher();
            } catch (\Throwable $exception) {
                $this->dispatcher = null;
            }
        }
    }

	/**
	 * Validates file upload against security constraints and returns validation error message if any.
	 *
	 * This method performs multiple security checks including:
	 * - File upload success verification
	 * - File size limits enforcement
	 * - File type/MIME checking against allowed types
	 * - File extension validation
	 * - MIME type verification using finfo
	 * - Image dimension validation for image files
	 *
	 * @param   array<string, mixed>  $field  Field configuration array containing validation rules
	 * @param   array<string, mixed>  $files  Files array from request containing upload data
	 *
	 * @return ?string Error message if validation fails, null if validation passes
	 * @since 1.0.0
	 */
	public function validate(array $field, array $files): ?string
    {
        $name = $field['name'] ?? '';

        if ($name === '') {
            return null;
        }

        $file = $this->extractUploadedFile($name, $files);

        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ((int) ($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
            return Text::_('COM_NXPEASYFORMS_ERROR_FILE_UPLOAD_FAILED');
        }

        $fieldMaxMb = isset($field['maxFileSize']) ? (int) $field['maxFileSize'] : 5;
        $fieldMaxMb = max(1, min(50, $fieldMaxMb));
        $maxSizeBytes = $fieldMaxMb * 1024 * 1024;
        $maxSizeBytes = (int) $this->filterValue(
            'onNxpEasyFormsFilterMaxUploadSize',
            $maxSizeBytes,
            ['field' => $field]
        );

        if (($file['size'] ?? 0) > $maxSizeBytes) {
            return Text::sprintf('COM_NXPEASYFORMS_ERROR_FILE_SIZE_LIMIT', $fieldMaxMb);
        }

        $allowedTypes = $this->filterValue(
            'onNxpEasyFormsFilterAllowedFileTypes',
            self::ALLOWED_FILE_TYPES,
            ['field' => $field]
        );

        if (!is_array($allowedTypes) || empty($allowedTypes)) {
            $allowedTypes = self::ALLOWED_FILE_TYPES;
        }

        // Log warning if plugins tried to add dangerous types (check BEFORE removing)
        $dangerousAttempts = array_intersect($allowedTypes, self::FORBIDDEN_MIME_TYPES);
        if (!empty($dangerousAttempts)) {
            try {
                Factory::getApplication()->getLogger()->warning(
                    'NXP Easy Forms: Plugin attempted to allow dangerous MIME types: '
                    . implode(', ', $dangerousAttempts)
                );
            } catch (\Throwable $e) {
                // Ignore logging errors
            }
        }

        // Remove any dangerous MIME types that plugins might have tried to add
        $allowedTypes = array_diff($allowedTypes, self::FORBIDDEN_MIME_TYPES);

        $fieldAccept = isset($field['accept']) && is_string($field['accept']) ? $field['accept'] : '';
        $fieldAllowedTypes = $this->filterAcceptTypes($fieldAccept, $allowedTypes);
        $finalAllowedTypes = !empty($fieldAllowedTypes) ? $fieldAllowedTypes : $allowedTypes;

        $tmpName = $file['tmp_name'] ?? '';
        $detectedMime = $this->detectMimeType($tmpName);

        if ($detectedMime === null || !in_array($detectedMime, $finalAllowedTypes, true)) {
            return Text::_('COM_NXPEASYFORMS_ERROR_FILE_TYPE_NOT_ALLOWED');
        }

        $originalName = isset($file['name']) && is_string($file['name']) ? $file['name'] : '';
        if (!$this->isExtensionAllowed($originalName, $detectedMime)) {
            return Text::_('COM_NXPEASYFORMS_ERROR_FILE_CONTENT_MISMATCH');
        }

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);

            if ($finfo !== false) {
                $finfoMime = finfo_file($finfo, $tmpName);
                finfo_close($finfo);

                if ($finfoMime !== false && !in_array($finfoMime, $finalAllowedTypes, true)) {
                    return Text::_('COM_NXPEASYFORMS_ERROR_FILE_CONTENT_MISMATCH');
                }
            }
        }

        if (str_starts_with($detectedMime, 'image/')) {
            $imageInfo = @getimagesize($tmpName);

            if ($imageInfo === false) {
                return Text::_('COM_NXPEASYFORMS_ERROR_IMAGE_INVALID');
            }

            [$width, $height] = $imageInfo;
            $maxDimension = (int) $this->filterValue(
                'onNxpEasyFormsFilterMaxImageDimension',
                4096,
                ['field' => $field]
            );

            if ($width > $maxDimension || $height > $maxDimension) {
                return Text::sprintf(
                    'COM_NXPEASYFORMS_ERROR_IMAGE_DIMENSIONS',
                    $maxDimension,
                    $maxDimension
                );
            }
        }

        return null;
    }

	/**
	 * Extracts uploaded file data for a given field name from the files array.
	 * Handles both single file uploads and multi-file upload structures.
	 *
	 * @param   string                $name   The field name to extract file data for
	 * @param   array<string, mixed>  $files  The files array from the request
	 *
	 * @return array<string, mixed>|null File data array if found, null if not found
	 * @since 1.0.0
	 */
	public function extractUploadedFile(string $name, array $files): ?array
    {
        if (isset($files[$name]) && isset($files[$name]['tmp_name'])) {
            return $files[$name];
        }

        if (!isset($files['files'])) {
            return null;
        }

        $group = $files['files'];

        if (
            isset($group['name'][$name], $group['tmp_name'][$name], $group['error'][$name], $group['size'][$name])
        ) {
            return [
                'name' => $group['name'][$name],
                'type' => $group['type'][$name] ?? '',
                'tmp_name' => $group['tmp_name'][$name],
                'error' => $group['error'][$name],
                'size' => $group['size'][$name],
            ];
        }

        return null;
    }

    /**
     * Proxy to get the allowed file types.
     * @return array<int, string>
     * @since 1.0.0
     */
    public static function getAllowedTypes(): array
    {
        return self::ALLOWED_FILE_TYPES;
    }

	/**
	 * Filters a value through the dispatcher event system.
	 * Allows modification of values via plugins/events while maintaining the original if no dispatcher exists.
	 *
	 * @param   string                $eventName  Name of the event to dispatch
	 * @param   mixed                 $value      The value to filter
	 * @param   array<string, mixed>  $context    Additional context data to pass to event handlers
	 *
	 * @return mixed The filtered value, or original if no dispatcher available
	 * @since 1.0.0
	 */
	private function filterValue(string $eventName, $value, array $context = []): mixed {
        if ($this->dispatcher === null) {
            return $value;
        }

        $payload = ['value' => &$value] + $context;
        $event = new Event($eventName, $payload);
        $this->dispatcher->dispatch($event->getName(), $event);

        return $event['value'] ?? $value;
    }

	/**
	 * Filters accepted file types based on HTML input accept attribute and allowed types list.
	 * Returns array of matching MIME types that are allowed.
	 *
	 * @param   string              $accept        HTML input accept attribute value
	 * @param   array<int, string>  $allowedTypes  List of allowed MIME types
	 *
	 * @return array<int, string> Array of matched allowed MIME types
	 * @since 1.0.0
	 */
	private function filterAcceptTypes(string $accept, array $allowedTypes): array
    {
        if ($accept === '') {
            return [];
        }

        $parts = array_map(
            static fn (string $segment): string => strtolower(trim($segment)),
            array_filter(explode(',', $accept))
        );

        $matched = array_values(array_intersect($parts, $allowedTypes));

        return $matched;
    }

	/**
	 * Detects and returns the MIME type of a given file.
	 *
	 * This method determines the MIME type using available PHP functions such as
	 * `finfo_open` and `mime_content_type`. If these functions are not available or
	 * if the file path is invalid or empty, it will return null.
	 *
	 * @param   string  $path  The absolute path to the file whose MIME type needs to be detected.
	 *
	 * @return string|null The MIME type of the file if detected successfully, or null otherwise.
	 * @since 1.0.0
	 */
    public function detectMimeType(string $path): ?string
    {
        if ($path === '' || !is_file($path)) {
            return null;
        }

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);

            if ($finfo !== false) {
                $mime = finfo_file($finfo, $path);
                finfo_close($finfo);

                if (is_string($mime) && $mime !== '') {
                    return $mime;
                }
            }
        }

        if (function_exists('mime_content_type')) {
            $mime = mime_content_type($path);

            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        }

        return null;
    }

	/**
	 * Determines whether the file extension of a given filename is allowed based on its MIME type.
	 * Includes comprehensive checks for dangerous extensions and double-extension attacks.
	 *
	 * @param   string  $originalName  The original name of the file.
	 * @param   string  $mime          The MIME type of the file.
	 *
	 * @return bool Returns true if the file extension is allowed, false otherwise.
	 * @since 1.0.0
	 */
    private function isExtensionAllowed(string $originalName, string $mime): bool
    {
        // Reject empty filenames
        if ($originalName === '') {
            return false;
        }

        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));

        // Reject files without extension
        if ($extension === '') {
            return false;
        }

        // Check against dangerous extensions list
        foreach (self::DANGEROUS_EXTENSIONS as $dangerous) {
            if ($extension === $dangerous || str_contains($extension, $dangerous)) {
                return false;
            }
        }

        // Check for double extensions (e.g., file.php.txt, file.exe.jpg)
        $allExtensions = [];
        $filename = $originalName;
        while (($ext = pathinfo($filename, PATHINFO_EXTENSION)) !== '') {
            $allExtensions[] = strtolower($ext);
            $filename = pathinfo($filename, PATHINFO_FILENAME);
        }

        foreach ($allExtensions as $ext) {
            foreach (self::DANGEROUS_EXTENSIONS as $dangerous) {
                if ($ext === $dangerous) {
                    return false;
                }
            }
        }

        // MIME type must be in our allowed list
        $allowed = self::MIME_EXTENSION_MAP[$mime] ?? null;

        if ($allowed === null) {
            // Unknown MIME type - reject by default for security
            return false;
        }

        return in_array($extension, $allowed, true);
    }

	/**
	 * Retrieves the preferred file extension for a given MIME type.
	 *
	 * @param   string  $mime  The MIME type for which the preferred extension is required.
	 *
	 * @return string|null Returns the preferred file extension if available, or null if no extension is associated with the MIME type.
	 * @since 1.0.0
	 */
    public static function getPreferredExtension(string $mime): ?string
    {
        $allowed = self::MIME_EXTENSION_MAP[$mime] ?? null;

        if (empty($allowed)) {
            return null;
        }

        return $allowed[0];
    }
}
