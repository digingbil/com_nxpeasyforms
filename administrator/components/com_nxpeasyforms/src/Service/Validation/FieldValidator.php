<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Service\Validation;

use Joomla\CMS\Language\Text;
use Joomla\Component\Nxpeasyforms\Administrator\Support\Sanitizer;


// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Validates and sanitizes incoming field payloads from form submissions.
 * This class handles validation and sanitization of various field types including:
 * text, email, telephone, textarea, checkbox, select, radio, date, password, and file uploads.
 * It processes the submitted data against field configuration rules and returns cleaned data
 * along with any validation errors encountered.
 * @since 1.0.0
 */
final class FieldValidator
{
    private FileValidator $fileValidator;

    public function __construct(?FileValidator $fileValidator = null)
    {
        $this->fileValidator = $fileValidator ?? new FileValidator();
    }

    /**
     * Validate all configured fields against submission payload.
     *
     * @param array<int, array<string, mixed>> $fields
     * @param array<string, mixed> $data
     * @param array<string, mixed> $files
     * @since 1.0.0
     */
    public function validateAll(array $fields, array $data, array $files): ValidationResult
    {
        $clean = [];
        $errors = [];
        $fieldMeta = [];
        $fileFieldsToProcess = [];

        foreach ($fields as $field) {
            $type = $field['type'] ?? 'text';
            $name = $field['name'] ?? null;

            if (!$name) {
                continue;
            }

            if (in_array($type, ['button', 'custom_text'], true)) {
                continue;
            }

            $required = isset($field['required']) ? (bool) $field['required'] : true;

            if ($type === 'file') {
                $error = $this->fileValidator->validate($field, $files);

                if ($required && $error === null) {
                    $file = $this->fileValidator->extractUploadedFile($name, $files);
                    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                        $error = Text::_('COM_NXPEASYFORMS_VALIDATION_FIELD_REQUIRED');
                    }
                }

                if ($error) {
                    $errors[$name] = $error;
                } else {
                    $fileFieldsToProcess[] = $field;
                }

                $clean[$name] = '';
                $fieldMeta[] = [
                    'name' => $name,
                    'label' => $field['label'] ?? $name,
                    'value' => '',
                    'type' => $type,
                    'meta' => [],
                ];

                continue;
            }

            $raw = $data[$name] ?? null;

            [$value, $error] = $this->sanitizeAndValidate($type, $raw, $field);

            if ($required && $this->isValueMissing($type, $raw, $value)) {
                $error = Text::_('COM_NXPEASYFORMS_VALIDATION_FIELD_REQUIRED');
            }

            if ($error) {
                $errors[$name] = $error;
            }

            $clean[$name] = $value;

            $fieldMeta[] = [
                'name' => $name,
                'label' => $field['label'] ?? $name,
                'value' => $value,
                'type' => $type,
                'meta' => [],
            ];
        }

        return new ValidationResult($clean, $errors, $fieldMeta);
    }

    /**
     * Sanitize and validate a field value based on its type.
     *
     * @param string $type
     * @param mixed $raw
     * @param array<string, mixed> $field
     *
     * @return array{0: mixed, 1: ?string}
     * @since 1.0.0
     */
    public function sanitizeAndValidate(string $type, $raw, array $field): array
    {
        switch ($type) {
            case 'email':
                $value = Sanitizer::cleanEmail(is_string($raw) ? $raw : '');
                if ($value === '' || !Sanitizer::isValidEmail($value)) {
                    return [$value, Text::_('COM_NXPEASYFORMS_VALIDATION_EMAIL_INVALID')];
                }

                return [$value, null];

            case 'tel':
                $value = is_string($raw) ? Sanitizer::cleanText($raw) : '';
                if ($value !== '' && !preg_match('/^[\d\s\(\)\-\+\.ext#]+$/i', $value)) {
                    return ['', Text::_('COM_NXPEASYFORMS_VALIDATION_PHONE_INVALID')];
                }

                return [$value, null];

            case 'textarea':
                $value = is_string($raw) ? Sanitizer::cleanTextarea($raw) : '';

                return [$value, null];

            case 'checkbox':
                $checked = $this->isCheckboxChecked($raw);

                return [
                    $checked ? Text::_('COM_NXPEASYFORMS_BOOLEAN_YES') : Text::_('COM_NXPEASYFORMS_BOOLEAN_NO'),
                    null,
                ];

            case 'select':
                return $this->validateSelect($raw, $field);

            case 'radio':
                return $this->validateRadio($raw, $field);

            case 'date':
                return $this->validateDate($raw);

            case 'password':
                $value = is_string($raw) ? Sanitizer::cleanText($raw) : '';

                return [$value, null];

            case 'text':
            case 'hidden':
            default:
                $value = is_string($raw) ? Sanitizer::cleanText($raw) : '';

                return [$value, null];
        }
    }

    /**
     * Validate a select field.
     *
     * @param mixed $raw
     * @param array<string, mixed> $field
     *
     * @return array{0: mixed, 1: ?string}
     * @since 1.0.0
     */
    private function validateSelect($raw, array $field): array
    {
        $options = isset($field['options']) && is_array($field['options']) ? $field['options'] : [];
        $multiple = !empty($field['multiple']);

        if ($multiple) {
            $values = [];

            if (is_array($raw)) {
                $values = Sanitizer::cleanTextArray(array_map('strval', $raw));
            } elseif (is_string($raw) && $raw !== '') {
                $values = [Sanitizer::cleanText($raw)];
            }

            $invalid = array_diff($values, $options);

            if (!empty($invalid)) {
                return [[], Text::_('COM_NXPEASYFORMS_VALIDATION_SELECTION_INVALID')];
            }

            return [$values, null];
        }

        $value = is_string($raw) ? Sanitizer::cleanText($raw) : '';
        if ($value !== '' && !in_array($value, $options, true)) {
            return ['', Text::_('COM_NXPEASYFORMS_VALIDATION_SELECTION_INVALID')];
        }

        return [$value, null];
    }

    /**
     * Validate a radio field.
     *
     * @param mixed $raw
     * @param array<string, mixed> $field
     *
     * @return array{0: string, 1: ?string}
     * @since 1.0.0
     */
    private function validateRadio($raw, array $field): array
    {
        $options = isset($field['options']) && is_array($field['options']) ? $field['options'] : [];
        $value = is_string($raw) ? Sanitizer::cleanText($raw) : '';

        if ($value !== '' && !in_array($value, $options, true)) {
            return ['', Text::_('COM_NXPEASYFORMS_VALIDATION_SELECTION_INVALID')];
        }

        return [$value, null];
    }

    /**
     * Validate a date field.
     *
     * @param mixed $raw
     *
     * @return array{0: string, 1: ?string}
     * @since 1.0.0
     */
    private function validateDate($raw): array
    {
        $value = is_string($raw) ? trim($raw) : '';

        if ($value === '') {
            return ['', null];
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        if (!$date || $date->format('Y-m-d') !== $value) {
            return ['', Text::_('COM_NXPEASYFORMS_VALIDATION_DATE_INVALID')];
        }

        return [$value, null];
    }

	/**
	 * Check if a field value is missing.
	 *
	 * @param   string  $type
	 * @param   mixed   $raw
	 * @param   mixed   $value
	 *
	 * @return bool
	 * @since 1.0.0
	 */
    public function isValueMissing(string $type, $raw, $value): bool
    {
        if ($type === 'checkbox') {
            return !$this->isCheckboxChecked($raw);
        }

        if ($type === 'select' && is_array($value)) {
            return count($value) === 0;
        }

        return $value === '' || $value === null || $value === [];
    }

    /**
     * Check if a checkbox is checked.
     *
     * @param mixed $raw
     * @since 1.0.0
     */
    private function isCheckboxChecked($raw): bool
    {
        if (is_array($raw)) {
            foreach ($raw as $entry) {
                if ($this->isCheckboxChecked($entry)) {
                    return true;
                }
            }

            return false;
        }

        if (is_bool($raw)) {
            return $raw;
        }

        if (is_string($raw)) {
            $normalized = strtolower($raw);

            return !in_array($normalized, ['', '0', 'false', 'off'], true);
        }

        if ($raw === null) {
            return false;
        }

        return (bool) $raw;
    }
}
