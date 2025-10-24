<?php

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

/**
 * Performs security and integrity checks for uploaded files.
 */
final class FileValidator
{
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
     * Map of mime-type => list of safe extensions.
     *
     * @var array<string, array<int, string>>
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
     * @param array<string, mixed> $field
     * @param array<string, mixed> $files
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
     * @param string $name
     * @param array<string, mixed> $files
     *
     * @return array<string, mixed>|null
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
     * @return array<int, string>
     */
    public static function getAllowedTypes(): array
    {
        return self::ALLOWED_FILE_TYPES;
    }

    /**
     * @param array<string, mixed> $context
     * @return mixed
     */
    private function filterValue(string $eventName, $value, array $context = [])
    {
        if ($this->dispatcher === null) {
            return $value;
        }

        $payload = ['value' => &$value] + $context;
        $event = new Event($eventName, $payload);
        $this->dispatcher->dispatch($event->getName(), $event);

        return $event['value'] ?? $value;
    }

    /**
     * @param string $accept
     * @param array<int, string> $allowedTypes
     *
     * @return array<int, string>
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

    private function isExtensionAllowed(string $originalName, string $mime): bool
    {
        if ($originalName === '') {
            return true;
        }

        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));

        if ($extension === '' || strpos($extension, 'php') !== false) {
            return false;
        }

        $allowed = self::MIME_EXTENSION_MAP[$mime] ?? null;

        if ($allowed === null) {
            return true;
        }

        return in_array($extension, $allowed, true);
    }

    public static function getPreferredExtension(string $mime): ?string
    {
        $allowed = self::MIME_EXTENSION_MAP[$mime] ?? null;

        if ($allowed === null || empty($allowed)) {
            return null;
        }

        return $allowed[0];
    }
}
