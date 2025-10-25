<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Service\File;

use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
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
     * @param array<string, mixed> $field
     * @param array<string, mixed> $files
     *
     * @return array{0: string, 1: array<string, mixed>, 2: ?string}
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

    private function ensureStorageDirectory(): void
    {
        if (Folder::exists($this->basePath)) {
            return;
        }

        if (!Folder::create($this->basePath)) {
            throw new \RuntimeException(Text::_('COM_NXPEASYFORMS_ERROR_FILE_DIRECTORY'));
        }
    }

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
