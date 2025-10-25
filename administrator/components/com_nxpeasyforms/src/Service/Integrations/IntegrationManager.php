<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations;


// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Registry for integration dispatchers.
 * Manages the registration and retrieval of integration dispatchers.
 * Each dispatcher has a unique identifier and implements the IntegrationDispatcherInterface.
 * Used to dispatch form submissions to external integrations like webhooks.
 * @see   IntegrationDispatcherInterface For the contract that all dispatchers must implement
 * @since 1.0.0
 */
final class IntegrationManager
{
    /**
     * @var array<string, IntegrationDispatcherInterface>
     */
    private array $dispatchers;

    public function __construct(array $dispatchers = [])
    {
        $this->dispatchers = $dispatchers;
    }

    public function register(string $id, IntegrationDispatcherInterface $dispatcher): void
    {
        $this->dispatchers[$id] = $dispatcher;
    }

    public function get(string $id): ?IntegrationDispatcherInterface
    {
        return $this->dispatchers[$id] ?? null;
    }
}
