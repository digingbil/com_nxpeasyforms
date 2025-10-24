<?php

declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Tests\Integrations;

use Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations\IntegrationDispatcherInterface;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations\IntegrationManager;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations\IntegrationQueue;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

final class IntegrationQueueTest extends TestCase
{
    public function testShouldQueueAsynchronousIntegrations(): void
    {
        $queue = new IntegrationQueue(new Psr16Cache(new ArrayAdapter()));

        $this->assertTrue($queue->shouldQueue('webhook'));
        $this->assertTrue($queue->shouldQueue('zapier'));
        $this->assertFalse($queue->shouldQueue('custom_sync'));
    }

    public function testProcessDispatchesQueuedJobs(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $queue = new IntegrationQueue($cache);

        $manager = new IntegrationManager([
            'webhook' => new RecordingDispatcher(),
        ]);

        $queue->enqueue(
            'webhook',
            ['endpoint' => 'https://example.com'],
            ['id' => 1],
            ['name' => 'Alice'],
            [],
            []
        );

        $queue->process($manager);

        /** @var RecordingDispatcher $dispatcher */
        $dispatcher = $manager->get('webhook');
        $this->assertSame(1, $dispatcher->dispatchCount);
    }
}

final class RecordingDispatcher implements IntegrationDispatcherInterface
{
    public int $dispatchCount = 0;

    public function dispatch(
        array $settings,
        array $form,
        array $payload,
        array $context,
        array $fieldMeta
    ): void {
        $this->dispatchCount++;
    }
}
