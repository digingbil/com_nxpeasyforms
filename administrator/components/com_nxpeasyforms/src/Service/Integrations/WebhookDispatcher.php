<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations;

use Joomla\CMS\Language\Text;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Security\EndpointValidator;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\Event;


use function hash_hmac;
use function json_encode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Dispatches payloads to generic webhooks with optional HMAC signing.
 */
final class WebhookDispatcher implements IntegrationDispatcherInterface
{
    private EndpointValidator $validator;

    private HttpClient $client;

    private ?DispatcherInterface $dispatcher;

    public function __construct(
        ?EndpointValidator $validator = null,
        ?HttpClient $client = null,
        ?DispatcherInterface $dispatcher = null
    ) {
        $this->validator = $validator ?? new EndpointValidator();
        $this->client = $client ?? new HttpClient();
        $this->dispatcher = $dispatcher;
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

        $target = $this->validator->validate($endpoint);
        if ($target === null) {
            $this->logError($endpoint, Text::_('COM_NXPEASYFORMS_WEBHOOK_ENDPOINT_INVALID'), $form, $payload, $context);
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
            'integration' => 'webhook',
        ];

        $body = $this->filterPayload('onNxpEasyFormsFilterWebhookPayload', $body, $form, $payload, $context, $fieldMeta);
        $jsonBody = json_encode($body, JSON_THROW_ON_ERROR);

        $headers = [
            'Content-Type' => 'application/json',
        ];

        $secret = isset($settings['secret']) ? (string) $settings['secret'] : '';
        if ($secret !== '') {
            $headers['X-NXP-Easy-Forms-Signature'] = hash_hmac('sha256', $jsonBody, $secret);
        }

        try {
            $response = $this->client->post($target, $jsonBody, $headers);
        } catch (\Throwable $exception) {
            $this->logError($target, $exception->getMessage(), $form, $payload, $context);
            return;
        }

        $code = (int) $response->code;
        if ($code < 200 || $code >= 400) {
            $this->logError($target, sprintf('HTTP %d', $code), $form, $payload, $context);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $form
     * @param array<string, mixed> $submission
     * @param array<string, mixed> $context
     * @param array<int, array<string, mixed>> $fieldMeta
     *
     * @return array<string, mixed>
     */
    private function filterPayload(
        string $eventName,
        array $payload,
        array $form,
        array $submission,
        array $context,
        array $fieldMeta
    ): array {
        if ($this->dispatcher === null) {
            return $payload;
        }

        $event = new Event($eventName, [
            'payload' => &$payload,
            'form' => $form,
            'submission' => $submission,
            'context' => $context,
            'field_meta' => $fieldMeta,
        ]);

        $this->dispatcher->dispatch($event->getName(), $event);

        return $payload;
    }

    /**
     * @param array<string, mixed> $form
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $context
     */
    private function logError(string $endpoint, string $message, array $form, array $payload, array $context): void
    {
        if ($this->dispatcher !== null) {
            $event = new Event('onNxpEasyFormsWebhookFailed', [
                'endpoint' => $endpoint,
                'integration' => 'webhook',
                'error' => $message,
                'context' => $context,
                'form' => $form,
                'payload' => $payload,
            ]);
            $this->dispatcher->dispatch($event->getName(), $event);
        }
    }
}
