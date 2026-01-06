<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations;

use Joomla\CMS\Uri\Uri;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Rendering\TemplateRenderer;
use Joomla\Component\Nxpeasyforms\Administrator\Support\Sanitizer;
use Joomla\Component\Nxpeasyforms\Administrator\Support\Secrets;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\Event;


use function array_filter;
use function array_key_exists;
use function filter_var;
use function is_array;
use function is_string;
use function rawurlencode;
use function sprintf;
use function trim;

use const FILTER_VALIDATE_EMAIL;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

final class HubspotDispatcher implements IntegrationDispatcherInterface
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

	/**
	 * Dispatches form data to HubSpot using their Forms API.
	 *
	 * @param   array  $settings   Configuration settings including HubSpot access token, portal ID and form GUID
	 * @param   array  $form       Form data containing title and other form properties
	 * @param   array  $payload    Form submission data to be sent to HubSpot
	 * @param   array  $context    Contextual data about the submission
	 * @param   array  $fieldMeta  Metadata about the form fields
	 *
	 * @throws \JsonException When JSON encoding/decoding fails
	 * @since 1.0.0
	 */
	public function dispatch(
        array $settings,
        array $form,
        array $payload,
        array $context,
        array $fieldMeta
    ): void {
        $tokenEncrypted = isset($settings['access_token']) ? (string) $settings['access_token'] : '';
        $accessToken = $tokenEncrypted !== '' ? Secrets::decrypt($tokenEncrypted) : '';

        if ($accessToken === '') {
            return;
        }

        $portalId = isset($settings['portal_id']) ? trim((string) $settings['portal_id']) : '';
        $formGuid = isset($settings['form_guid']) ? trim((string) $settings['form_guid']) : '';

        if ($portalId === '' || $formGuid === '') {
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

        $fields = [
            ['name' => 'email', 'value' => $email],
        ];

        $mappings = isset($settings['field_mappings']) && is_array($settings['field_mappings'])
            ? $settings['field_mappings']
            : [];

        foreach ($mappings as $mapping) {
            if (!is_array($mapping)) {
                continue;
            }

            $hubField = isset($mapping['hubspot_field']) ? (string) $mapping['hubspot_field'] : '';
            $formField = isset($mapping['form_field']) ? (string) $mapping['form_field'] : '';

            if ($hubField === '' || $formField === '' || !array_key_exists($formField, $payload)) {
                continue;
            }

            $value = $this->renderer->normalizeValue($payload[$formField]);

            if ($value === '') {
                continue;
            }

            $fields[] = [
                'name' => $hubField,
                'value' => $value,
            ];
        }

        $hubspotContext = [
            'pageUri' => Uri::root(),
            'pageName' => isset($form['title']) ? (string) $form['title'] : '',
        ];

        if (isset($_COOKIE['hubspotutk'])) {
            $hubspotContext['hutk'] = Sanitizer::cleanText((string) $_COOKIE['hubspotutk']);
        }

        $body = [
            'fields' => $fields,
            'context' => array_filter($hubspotContext),
        ];

        if (!empty($settings['legal_consent'])) {
            $consentText = isset($settings['consent_text']) && is_string($settings['consent_text']) && $settings['consent_text'] !== ''
                ? Sanitizer::cleanTextarea($settings['consent_text'])
                : 'Consent recorded via NXP Easy Forms submission.';

            $body['legalConsentOptions'] = [
                'consent' => [
                    'consentToProcess' => true,
                    'text' => $consentText,
                    'communications' => [],
                ],
            ];
        }

        $body = $this->filterPayload('onNxpEasyFormsFilterHubspotPayload', $body, $form, $payload, $context, $fieldMeta);

        $endpoint = sprintf(
            'https://api.hsforms.com/submissions/v3/integration/submit/%s/%s',
            rawurlencode($portalId),
            rawurlencode($formGuid)
        );

        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
        ];

        $this->client->sendJson($endpoint, $body, 'POST', $headers, 10);
    }

	/**
	 * Filters the payload through event dispatcher to allow modifications.
	 * Plugins could hook into this event to modify the payload before it's sent to HubSpot.
	 *
	 * @param   string                            $eventName   Name of event to dispatch
	 * @param   array<string, mixed>              $payload     Payload data to filter
	 * @param   array<string, mixed>              $form        Form data and configuration
	 * @param   array<string, mixed>              $submission  Form submission data
	 * @param   array<string, mixed>              $context     Contextual information
	 * @param   array<int, array<string, mixed>>  $fieldMeta   Field metadata
	 *
	 * @return array<string, mixed> Filtered payload
	 * @since 1.0.0
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
