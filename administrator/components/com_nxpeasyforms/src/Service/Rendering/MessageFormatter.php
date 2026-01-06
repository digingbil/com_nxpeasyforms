<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Service\Rendering;

use Joomla\CMS\Language\Text;


use function implode;
use function is_array;
use function is_string;
use function sprintf;
use function strtr;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Formats submission data for various messaging channels.
 * Supports Slack, Markdown and plaintext output formats.
 * @since 1.0.0
 */
final class MessageFormatter
{
    private TemplateRenderer $renderer;

    public function __construct(?TemplateRenderer $renderer = null)
    {
        $this->renderer = $renderer ?? new TemplateRenderer();
    }

    /**
     * Builds the headline for the notification.
     * 
     * @param array<string, mixed> $form
     * @since 1.0.0
     */
    public function buildHeadline(array $form): string
    {
        $title = isset($form['title']) && is_string($form['title']) && $form['title'] !== ''
            ? $form['title']
            : Text::_('COM_NXPEASYFORMS');

        return sprintf(Text::_('COM_NXPEASYFORMS_NOTIFICATION_HEADLINE'), $title);
    }

	/**
	 * Formats form data into a standardized array format for messaging.
	 *
	 * @param   array<string, mixed>              $payload    The form submission payload data
	 * @param   array<int, array<string, mixed>>  $fieldMeta  Metadata for form fields
	 *
	 * @return array<int, array{label:string,value:string}> Array of formatted label/value pairs
	 * @since 1.0.0
	 */
	public function buildLines(array $payload, array $fieldMeta): array
    {
        $lines = [];

        foreach ($fieldMeta as $meta) {
            if (!is_array($meta)) {
                continue;
            }

            $name = isset($meta['name']) ? (string) $meta['name'] : '';
            $label = isset($meta['label']) && $meta['label'] !== '' ? (string) $meta['label'] : $name;

            if ($label === '') {
                continue;
            }

            $value = $payload[$name] ?? ($meta['value'] ?? '');

            $lines[] = [
                'label' => $label,
                'value' => $this->renderer->normalizeValue($value),
            ];
        }

        return $lines;
    }

    /**
     * Formats form data into a Slack-compatible message.
     *
     * @param array<int, array{label:string,value:string}> $lines
     * @since 1.0.0
     */
    public function formatForSlack(array $lines): string
    {
        $formatted = [];

        foreach ($lines as $line) {
            $value = $line['value'] !== '' ? $line['value'] : Text::_('COM_NXPEASYFORMS_EMAIL_EMPTY_VALUE');
            $formatted[] = sprintf(
                '*%s*: %s',
                $this->escapeSlack($line['label']),
                $this->escapeSlack($value)
            );
        }

        return implode("\n", $formatted);
    }

    /**
     * Formats form data into a Markdown-compatible message.
     *
     * @param array<int, array{label:string,value:string}> $lines
     * @since 1.0.0
     */
    public function formatForMarkdown(array $lines): string
    {
        if (empty($lines)) {
            return '';
        }

        $segments = [];

        foreach ($lines as $line) {
            $value = $line['value'] !== '' ? $line['value'] : Text::_('COM_NXPEASYFORMS_EMAIL_EMPTY_VALUE');
            $segments[] = sprintf('**%s**: %s', $line['label'], $value);
        }

        return implode("\n\n", $segments);
    }

    /**
     * Formats form data into a plaintext message.
     *
     * @param array<int, array{label:string,value:string}> $lines
     * @since 1.0.0
     */
    public function formatForPlaintext(array $lines, string $headline = ''): string
    {
        $parts = [];

        if ($headline !== '') {
            $parts[] = $headline;
        }

        foreach ($lines as $line) {
            $value = $line['value'] !== '' ? $line['value'] : Text::_('COM_NXPEASYFORMS_EMAIL_EMPTY_VALUE');
            $parts[] = sprintf('%s: %s', $line['label'], $value);
        }

        return implode("\n", $parts);
    }

	/**
	 * Escapes special characters for Slack messages.
	 *
	 * Replaces `&`, `<`, and `>` with their corresponding escaped entities to ensure proper rendering in Slack.
	 *
	 * @param   string  $value  The input string to be escaped.
	 *
	 * @return string The escaped string safe for use in Slack messages.
	 * @since 1.0.0
	 */
    private function escapeSlack(string $value): string
    {
        return strtr($value, [
            '&' => '&amp;',
            '<' => '&lt;',
            '>' => '&gt;',
        ]);
    }
}
