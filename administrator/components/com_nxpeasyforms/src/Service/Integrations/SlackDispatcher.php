<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations;

use Joomla\CMS\Factory;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Rendering\MessageFormatter;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Rendering\TemplateRenderer;

use function is_string;
use function trim;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Sends form submissions to Slack using an incoming webhook.
 *
 * This service dispatches form data to Slack by formatting the payload into a message
 * and sending it to a configured Slack webhook URL. It supports custom message templates
 * and falls back to a default format if no template is provided.
 *
 * @since 1.0.0
 */
final class SlackDispatcher implements IntegrationDispatcherInterface
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
        $webhook = isset($settings['webhook_url']) ? trim((string) $settings['webhook_url']) : '';

        if ($webhook === '') {
            return;
        }

        $template = isset($settings['message_template']) && is_string($settings['message_template'])
            ? $settings['message_template']
            : '';

        $headline = $this->formatter->buildHeadline($form);

        if ($template !== '') {
            $message = $this->renderer->render($template, $form, $payload, $fieldMeta);
            if ($message === '') {
                $lines = $this->formatter->buildLines($payload, $fieldMeta);
                $message = $headline . "\n" . $this->formatter->formatForSlack($lines);
            }
        } else {
            $lines = $this->formatter->buildLines($payload, $fieldMeta);
            $formatted = $this->formatter->formatForSlack($lines);
            $message = $headline;
            if ($formatted !== '') {
                $message .= "\n" . $formatted;
            }
        }

        try {
            $this->client->sendJson($webhook, ['text' => $message], 'POST', [], 10);
        } catch (\Throwable $exception) {
            try {
                Factory::getApplication()->getLogger()->warning(
                    'NXP Easy Forms Slack dispatch failed: ' . $exception->getMessage(),
                    ['form_id' => $form['id'] ?? null]
                );
            } catch (\Throwable $e) {
                // Ignore logging errors
            }
        }
    }
}
