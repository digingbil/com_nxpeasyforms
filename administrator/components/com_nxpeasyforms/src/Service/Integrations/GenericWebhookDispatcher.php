<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations;

use Joomla\CMS\Factory;

use function trim;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Simple JSON webhook dispatcher for Zapier/Make style endpoints.
 * @since 1.0.0
 */
final class GenericWebhookDispatcher implements IntegrationDispatcherInterface
{
    private HttpClient $client;

    public function __construct(?HttpClient $client = null)
    {
        $this->client = $client ?? new HttpClient();
    }

	/**
	 * Dispatches a payload to the specified endpoint with contextual and metadata information.
	 *
	 * @param   array  $settings   An array of settings which includes the 'endpoint' key for target URL.
	 * @param   array  $form       An array containing form data, such as 'id' and 'title'.
	 * @param   array  $payload    An array representing the payload to be dispatched.
	 * @param   array  $context    An array providing contextual information related to the dispatch.
	 * @param   array  $fieldMeta  Metadata describing the fields included in the form or payload.
	 *
	 * @return void This method does not return a value.
	 * @since 1.0.0
	 */
    public function dispatch(
        array $settings,
        array $form,
        array $payload,
        array $context,
        array $fieldMeta
    ): void {
        $endpoint = isset($settings['endpoint'])
            ? (string) $settings['endpoint']
            : (string) ($settings['webhook_url'] ?? '');

        $endpoint = trim($endpoint);

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
            try {
                Factory::getApplication()->getLogger()->warning(
                    'NXP Easy Forms webhook dispatch failed: ' . $exception->getMessage(),
                    ['endpoint' => $endpoint, 'form_id' => $form['id'] ?? null]
                );
            } catch (\Throwable $e) {
                // Ignore logging errors
            }
        }
    }
}
