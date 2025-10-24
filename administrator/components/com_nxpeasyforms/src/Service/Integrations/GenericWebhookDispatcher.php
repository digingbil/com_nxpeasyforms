<?php

declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations;

/**
 * Simple JSON webhook dispatcher for Zapier/Make style endpoints.
 */
final class GenericWebhookDispatcher implements IntegrationDispatcherInterface
{
    private HttpClient $client;

    public function __construct(?HttpClient $client = null)
    {
        $this->client = $client ?? new HttpClient();
    }

    public function dispatch(
        array $settings,
        array $form,
        array $payload,
        array $context,
        array $fieldMeta
    ): void {
        $endpoint = isset($settings['endpoint']) ? (string) $settings['endpoint'] : '';

        if ($endpoint === '') {
            return;
        }

        $body = [
            'form' => [
                'id' => $form['id'] ?? null,
                'title' => $form['title'] ?? '',
            ],
            'submission' => $payload,
            'meta' => $fieldMeta,
            'context' => $context,
        ];

        try {
            $this->client->post($endpoint, $body, ['Content-Type' => 'application/json'], 10);
        } catch (\Throwable $exception) {
            // TODO: integrate logging service
        }
    }
}
