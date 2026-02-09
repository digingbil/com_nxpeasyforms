<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Service\Rendering;

use Joomla\CMS\Language\Text;


use function array_filter;
use function implode;
use function is_array;
use function is_bool;
use function is_object;
use function is_scalar;
use function json_encode;
use function preg_replace_callback;
use function trim;
use function strtr;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_UNICODE;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * TemplateRenderer handles the dynamic replacement of placeholders in templates with form submission values.
 * Supports both predefined placeholders (form_title, form_id, submission_json) and field-specific placeholders.
 * Field placeholders use the syntax {{field:field_name}} and are replaced with formatted field values.
 * @since 1.0.0
 */
final class TemplateRenderer
{
	/**
	 * Renders a template by replacing placeholders with corresponding values from form data.
	 *
	 * @param   string                            $template   The template string containing placeholders to be replaced
	 * @param   array<string, mixed>              $form       Array containing form details like 'id' and 'title'
	 * @param   array<string, mixed>              $payload    Array containing submitted field values
	 * @param   array<int, array<string, mixed>>  $fieldMeta  Array containing metadata for form fields
	 * @since 1.0.0
	 */
	public function render(
        string $template,
        array $form,
        array $payload,
        array $fieldMeta
    ): string {
        try {
            $submissionJson = json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            );
        } catch (\JsonException $exception) {
            $submissionJson = '{}';
        }

        $replacements = [
            '{{form_title}}' => isset($form['title']) ? (string) $form['title'] : '',
            '{{form_id}}' => isset($form['id']) ? (string) $form['id'] : '',
            '{{submission_json}}' => $submissionJson,
        ];

        $rendered = strtr($template, $replacements);

        $rendered = preg_replace_callback(
            '/{{field:([a-zA-Z0-9_\\-]+)}}/',
            function ($matches) use ($payload) {
                $fieldName = $matches[1];
                $value = $payload[$fieldName] ?? '';

                return $this->normalizeValue($value);
            },
            $rendered
        );

        return trim((string) $rendered);
    }

    /**
     * Normalizes a value for display in a template.
     *
     * @param mixed $value
     * @since 1.0.0
     */
    public function normalizeValue(mixed $value): string
    {
        if (is_array($value)) {
            $parts = [];

            foreach ($value as $item) {
                if (is_scalar($item) || $item === null) {
                    $parts[] = (string) $item;
                } elseif (is_array($item) || is_object($item)) {
                    try {
                        $json = json_encode($item, JSON_THROW_ON_ERROR);
                        $parts[] = $json;
                    } catch (\JsonException $exception) {
                        // Skip invalid structures
                    }
                }
            }

            $parts = array_filter($parts, static fn (string $part): bool => $part !== '');

            return implode(', ', $parts);
        }

        if (is_bool($value)) {
            return $value ? Text::_('JYES') : Text::_('JNO');
        }

        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        if (is_object($value)) {
            try {
                return json_encode($value, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                return '';
            }
        }

        return '';
    }
}
