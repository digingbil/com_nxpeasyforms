<?php

declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations;

use Joomla\Component\Nxpeasyforms\Administrator\Service\Rendering\MessageFormatter;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Rendering\TemplateRenderer;

use function is_string;
use function trim;

/**
 * Sends adaptive card style payloads to Microsoft Teams.
 */
final class TeamsDispatcher implements IntegrationDispatcherInterface
{
    private HttpClient $client;

    private MessageFormatter $formatter;

    private TemplateRenderer $renderer;

    public function __construct(
        ?HttpClient $client = null,
        ?MessageFormatter $formatter = null,
        ?TemplateRenderer $renderer = null
    ) {
        $this->client = $client ?? new HttpClient();
        $this->formatter = $formatter ?? new MessageFormatter();
        $this->renderer = $renderer ?? new TemplateRenderer();
    }

    public function dispatch(
        array $settings,
        array $form,
        array $payload,
        array $context,
        array $fieldMeta
    ): void {
        $endpoint = isset($settings['webhook_url']) ? trim((string) $settings['webhook_url']) : '';

        if ($endpoint === '') {
            return;
        }

        $title = isset($settings['card_title']) && is_string($settings['card_title']) && $settings['card_title'] !== ''
            ? $settings['card_title']
            : $this->formatter->buildHeadline($form);

        $template = isset($settings['message_template']) && is_string($settings['message_template'])
            ? $settings['message_template']
            : '';

        $lines = $this->formatter->buildLines($payload, $fieldMeta);

        if ($template !== '') {
            $text = $this->renderer->render($template, $form, $payload, $fieldMeta);
            if ($text === '') {
                $text = $this->formatter->formatForMarkdown($lines);
            }
        } else {
            $text = $this->formatter->formatForMarkdown($lines);
        }

        $facts = [];
        foreach ($lines as $line) {
            $facts[] = [
                'name' => $line['label'],
                'value' => $line['value'] !== '' ? $line['value'] : '—',
            ];
        }

        $body = [
            '@type' => 'MessageCard',
            '@context' => 'http://schema.org/extensions',
            'summary' => $title,
            'themeColor' => '0366D6',
            'title' => $title,
            'text' => $text !== '' ? $text : $title,
            'sections' => [
                [
                    'facts' => $facts,
                    'markdown' => true,
                ],
            ],
        ];

        try {
            $this->client->sendJson($endpoint, $body, 'POST', [], 10);
        } catch (\Throwable $exception) {
            // TODO logging
        }
    }
}
