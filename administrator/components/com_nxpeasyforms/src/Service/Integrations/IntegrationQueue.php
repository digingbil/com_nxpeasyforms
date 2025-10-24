<?php

declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations;

/**
 * Placeholder queue implementation that performs synchronous dispatch.
 */
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;

use function array_values;
use function defined;
use function in_array;
use function sys_get_temp_dir;

final class IntegrationQueue
{
    private const CACHE_KEY = 'com_nxpeasyforms.integration_queue';

    private const MAX_ATTEMPTS = 3;

    /**
     * @var array<string>
     */
    private array $asyncIntegrations = [
        'zapier',
        'make',
        'slack',
        'teams',
        'webhook',
        'mailchimp',
        'hubspot',
    ];

    private CacheInterface $cache;

    public function __construct(?CacheInterface $cache = null)
    {
        $this->cache = $cache ?? self::createDefaultCache();
    }

    public function shouldQueue(string $integrationId): bool
    {
        return in_array($integrationId, $this->asyncIntegrations, true);
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
        $queue = $this->cache->get(self::CACHE_KEY, []);

        $queue[] = [
            'integration_id' => $integrationId,
            'settings' => $settings,
            'form' => $form,
            'payload' => $payload,
            'context' => $context,
            'field_meta' => $fieldMeta,
            'attempts' => 0,
        ];

        $this->cache->set(self::CACHE_KEY, $queue);
    }

    public function process(IntegrationManager $manager, int $batchSize = 10): void
    {
        $queue = $this->cache->get(self::CACHE_KEY, []);

        if (empty($queue)) {
            return;
        }

        $processed = 0;
        $remaining = [];

        foreach ($queue as $job) {
            if ($processed >= $batchSize) {
                $remaining[] = $job;
                continue;
            }

            $processed++;

            try {
                $dispatcher = $manager->get($job['integration_id']);

                if ($dispatcher === null) {
                    continue;
                }

                $dispatcher->dispatch(
                    $job['settings'],
                    $job['form'],
                    $job['payload'],
                    $job['context'],
                    $job['field_meta']
                );
            } catch (\Throwable $exception) {
                $job['attempts'] = (int) $job['attempts'] + 1;

                if ($job['attempts'] < self::MAX_ATTEMPTS) {
                    $remaining[] = $job;
                }
            }
        }

        $this->cache->set(self::CACHE_KEY, array_values($remaining));
    }

    private static function createDefaultCache(): CacheInterface
    {
        $cacheDir = defined('JPATH_CACHE') ? JPATH_CACHE : sys_get_temp_dir() . '/joomla_cache';
        $adapter = new FilesystemAdapter('com_nxpeasyforms_queue', 0, $cacheDir);

        return new Psr16Cache($adapter);
    }
}
