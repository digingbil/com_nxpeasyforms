<?php

declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations;

/**
 * Contract for integration dispatchers.
 */
interface IntegrationDispatcherInterface
{
    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $form
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $context
     * @param array<int, array<string, mixed>> $fieldMeta
     */
    public function dispatch(
        array $settings,
        array $form,
        array $payload,
        array $context,
        array $fieldMeta
    ): void;
}
