<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations;

use Joomla\Component\Nxpeasyforms\Administrator\Service\Rendering\TemplateRenderer;
use Joomla\Component\Nxpeasyforms\Administrator\Support\Sanitizer;
use Joomla\Component\Nxpeasyforms\Administrator\Support\Secrets;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\Event;


use function array_key_exists;
use function base64_encode;
use function count;
use function explode;
use function filter_var;
use function in_array;
use function is_array;
use function is_string;
use function md5;
use function preg_replace;
use function rawurlencode;
use function sprintf;
use function strtolower;
use function trim;

use const FILTER_VALIDATE_EMAIL;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

final class MailchimpDispatcher implements IntegrationDispatcherInterface
{
    private HttpClient $client;

    private TemplateRenderer $renderer;

    private ?DispatcherInterface $dispatcher;

    public function __construct(
        ?HttpClient $client = null,
        ?TemplateRenderer $renderer = null,
        ?DispatcherInterface $dispatcher = null
    ) {
        $this->client = $client ?? new HttpClient();
        $this->renderer = $renderer ?? new TemplateRenderer();
        $this->dispatcher = $dispatcher;
    }

    public function dispatch(
        array $settings,
        array $form,
        array $payload,
        array $context,
        array $fieldMeta
    ): void {
        $apiKeyEncrypted = isset($settings['api_key']) ? (string) $settings['api_key'] : '';
        $apiKey = $apiKeyEncrypted !== '' ? Secrets::decrypt($apiKeyEncrypted) : '';

        if ($apiKey === '') {
            return;
        }

        $listId = isset($settings['list_id']) ? trim((string) $settings['list_id']) : '';
        if ($listId === '') {
            return;
        }

        $emailField = isset($settings['email_field']) ? (string) $settings['email_field'] : '';
        if ($emailField === '' || !array_key_exists($emailField, $payload)) {
            return;
        }

        $email = trim((string) $payload[$emailField]);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $dataCenter = $this->extractDatacenter($apiKey);
        if ($dataCenter === '') {
            return;
        }

        $memberHash = md5(strtolower($email));
        $endpoint = sprintf(
            'https://%s.api.mailchimp.com/3.0/lists/%s/members/%s',
            $dataCenter,
            rawurlencode($listId),
            $memberHash
        );

        $doubleOptIn = !empty($settings['double_opt_in']);
        $status = $doubleOptIn ? 'pending' : 'subscribed';

        $body = [
            'email_address' => $email,
            'status_if_new' => $status,
            'status' => $status,
            'email_type' => 'html',
            'merge_fields' => [],
        ];

        $firstField = isset($settings['first_name_field']) ? (string) $settings['first_name_field'] : '';
        if ($firstField !== '' && isset($payload[$firstField])) {
            $body['merge_fields']['FNAME'] = $this->renderer->normaliseValue($payload[$firstField]);
        }

        $lastField = isset($settings['last_name_field']) ? (string) $settings['last_name_field'] : '';
        if ($lastField !== '' && isset($payload[$lastField])) {
            $body['merge_fields']['LNAME'] = $this->renderer->normaliseValue($payload[$lastField]);
        }

        if (isset($settings['tags']) && is_array($settings['tags'])) {
            $tags = [];
            foreach ($settings['tags'] as $tag) {
                if (!is_string($tag)) {
                    continue;
                }

                $cleanTag = Sanitizer::cleanText($tag);
                if ($cleanTag !== '') {
                    $tags[] = $cleanTag;
                }
            }

            if (!empty($tags)) {
                $body['tags'] = $tags;
            }
        }

        $body = $this->filterPayload('onNxpEasyFormsFilterMailchimpPayload', $body, $form, $payload, $context, $fieldMeta);

        $headers = [
            'Authorization' => 'Basic ' . base64_encode('nxp:' . $apiKey),
        ];

        $this->client->sendJson($endpoint, $body, 'PUT', $headers, 10);
    }

    private function extractDatacenter(string $apiKey): string
    {
        $parts = explode('-', $apiKey);
        if (count($parts) < 2) {
            return '';
        }

        $candidate = strtolower(trim((string) end($parts)));

        return preg_replace('/[^a-z0-9]/', '', $candidate) ?? '';
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $form
     * @param array<string, mixed> $submission
     * @param array<string, mixed> $context
     * @param array<int, array<string, mixed>> $fieldMeta
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
}
