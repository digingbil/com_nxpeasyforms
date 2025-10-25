<?php
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
 * Formats submission data for messaging channels.
 */
final class MessageFormatter
{
    private TemplateRenderer $renderer;

    public function __construct(?TemplateRenderer $renderer = null)
    {
        $this->renderer = $renderer ?? new TemplateRenderer();
    }

    /**
     * @param array<string, mixed> $form
     */
    public function buildHeadline(array $form): string
    {
        $title = isset($form['title']) && is_string($form['title']) && $form['title'] !== ''
            ? $form['title']
            : Text::_('COM_NXPEASYFORMS');

        return sprintf(Text::_('COM_NXPEASYFORMS_NOTIFICATION_HEADLINE'), $title);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, array<string, mixed>> $fieldMeta
     *
     * @return array<int, array{label:string,value:string}>
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
                'value' => $this->renderer->normaliseValue($value),
            ];
        }

        return $lines;
    }

    /**
     * @param array<int, array{label:string,value:string}> $lines
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
     * @param array<int, array{label:string,value:string}> $lines
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
     * @param array<int, array{label:string,value:string}> $lines
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

    private function escapeSlack(string $value): string
    {
        return strtr($value, [
            '&' => '&amp;',
            '<' => '&lt;',
            '>' => '&gt;',
        ]);
    }
}
