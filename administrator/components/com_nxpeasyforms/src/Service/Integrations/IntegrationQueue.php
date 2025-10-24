<?php

declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations;

/**
 * Placeholder queue implementation that performs synchronous dispatch.
 */
final class IntegrationQueue
{
    /**
     * @var array<string>
     */
    private array $asyncIntegrations = [
        'zapier',
        'make',
        'slack',
        'teams',
        'webhook',
    ];

    public function shouldQueue(string $integrationId): bool
    {
        // Asynchronous processing not yet implemented; always return false.
        return false;
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $form
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $context
     * @param array<int, array<string, mixed>> $fieldMeta
     */
    public function enqueue(
        string $integrationId,
        array $settings,
        array $form,
        array $payload,
        array $context,
        array $fieldMeta
    ): void {
        // Future hook for asynchronous processing.
    }
}
