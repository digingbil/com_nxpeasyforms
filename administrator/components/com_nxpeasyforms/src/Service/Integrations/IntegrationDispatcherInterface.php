<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations;


// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Contract for integration dispatchers.
 * @since 1.0.0
 */
interface IntegrationDispatcherInterface
{
	/**
	 * Dispatches a payload to the specified endpoint with contextual and metadata information.
	 *
	 * @param   array<string, mixed>              $settings   Settings array, must contain 'endpoint' key for target URL
	 * @param   array<string, mixed>              $form       Form configuration array with id and title
	 * @param   array<string, mixed>              $payload    Data payload to be dispatched
	 * @param   array<string, mixed>              $context    Contextual information for the dispatch
	 * @param   array<int, array<string, mixed>>  $fieldMeta  Field metadata
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function dispatch(
		array $settings,
        array $form,
        array $payload,
        array $context,
        array $fieldMeta
    ): void;
}
