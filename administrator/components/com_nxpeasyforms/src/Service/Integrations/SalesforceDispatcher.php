<?php

declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations;

use Joomla\CMS\Uri\Uri;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Rendering\TemplateRenderer;
use Joomla\Component\Nxpeasyforms\Administrator\Support\Sanitizer;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\Event;

use function array_key_exists;
use function filter_var;
use function is_array;
use function sprintf;
use function trim;

use const FILTER_VALIDATE_EMAIL;

final class SalesforceDispatcher implements IntegrationDispatcherInterface
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
        $orgId = isset($settings['org_id']) ? trim((string) $settings['org_id']) : '';

        if ($orgId === '') {
            return;
        }

        $data = [
            'oid' => $orgId,
            'retURL' => Uri::root(),
        ];

        if (!empty($settings['lead_source'])) {
            $data['lead_source'] = Sanitizer::cleanText((string) $settings['lead_source']);
        }

        if (!empty($settings['assignment_rule_id'])) {
            $data['assignment_rule_id'] = Sanitizer::cleanText((string) $settings['assignment_rule_id']);
        }

        if (!empty($settings['debug_email']) && filter_var($settings['debug_email'], FILTER_VALIDATE_EMAIL)) {
            $data['debug'] = '1';
            $data['debugEmail'] = (string) $settings['debug_email'];
        }

        $mappings = isset($settings['mappings']) && is_array($settings['mappings']) ? $settings['mappings'] : [];

        foreach ($mappings as $mapping) {
            if (!is_array($mapping)) {
                continue;
            }

            $sfField = isset($mapping['salesforce_field']) ? trim((string) $mapping['salesforce_field']) : '';
            $formField = isset($mapping['form_field']) ? (string) $mapping['form_field'] : '';

            if ($sfField === '' || $formField === '' || !array_key_exists($formField, $payload)) {
                continue;
            }

            $data[$sfField] = $this->renderer->normaliseValue($payload[$formField]);
        }

        $data = $this->filterPayload('onNxpEasyFormsFilterSalesforcePayload', $data, $form, $payload, $context, $fieldMeta);

        $endpoint = 'https://webto.salesforce.com/servlet/servlet.WebToLead?encoding=UTF-8';

        $this->client->sendForm($endpoint, $data, 'POST', [], 10);
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
