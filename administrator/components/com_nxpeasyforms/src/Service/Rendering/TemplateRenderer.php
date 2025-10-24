<?php

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

/**
 * Renders template placeholders with submission values.
 */
final class TemplateRenderer
{
    /**
     * @param array<string, mixed> $form
     * @param array<string, mixed> $payload
     * @param array<int, array<string, mixed>> $fieldMeta
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

                return $this->normaliseValue($value);
            },
            $rendered
        );

        return trim((string) $rendered);
    }

    /**
     * @param mixed $value
     */
    public function normaliseValue($value): string
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
