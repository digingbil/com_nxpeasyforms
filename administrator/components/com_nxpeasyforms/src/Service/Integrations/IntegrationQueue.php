<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\Component\Nxpeasyforms\Administrator\Service\Cache\SimpleFileCache;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
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
	 * Enqueues a form submission for asynchronous integration dispatch.
	 *
	 * @param   array<string, mixed>              $settings   Integration settings containing endpoint URL
	 * @param   array<string, mixed>              $form       Form data with id and title
	 * @param   array<string, mixed>              $payload    Form submission payload
	 * @param   array<string, mixed>              $context    Contextual dispatch information
	 * @param   array<int, array<string, mixed>>  $fieldMeta  Field metadata
	 *
	 * @throws InvalidArgumentException
	 * @since 1.0.0
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


	/**
	 * Processes queued integration dispatches in batches.
	 *
	 * This method:
	 * - Retrieves pending jobs from the queue
	 * - Processes up to $batchSize jobs
	 * - Attempts to dispatch each job using the appropriate integration dispatcher
	 * - Handles failures with retry logic (up to MAX_ATTEMPTS)
	 * - Updates the queue by removing successful jobs and requeueing failed ones
	 *
	 * @param   IntegrationManager  $manager    Manager instance to retrieve integration dispatchers
	 * @param   int                 $batchSize  Maximum number of jobs to process in this batch (default: 10)
	 *
	 * @return  void
	 * @throws  InvalidArgumentException  If cache operations fail
	 * @since   1.0.0
	 */
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
        $namespace = 'com_nxpeasyforms_queue';

        if (class_exists(FilesystemAdapter::class) && class_exists(Psr16Cache::class)) {
            $adapter = new FilesystemAdapter($namespace, 0, $cacheDir);

            return new Psr16Cache($adapter);
        }

        $storageDir = rtrim($cacheDir, DIRECTORY_SEPARATOR) . '/' . $namespace;

        return new SimpleFileCache($storageDir);
    }
}
