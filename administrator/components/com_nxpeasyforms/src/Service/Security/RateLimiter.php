<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Service\Security;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Language\Text;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Cache\SimpleFileCache;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Exception\SubmissionException;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;


use function defined;
use function is_array;
use function md5;
use function sys_get_temp_dir;
use function time;

/**
 * PSR-16 backed rate limiter implementation for throttling form submissions.
 * Prevents abuse by limiting the number of submissions per IP address within a specified time period.
 * Uses PSR-16 compliant caching to maintain submission counters.
 * @since 1.0.0
 */
final class RateLimiter
{
    private CacheInterface $cache;

    private string $keyPrefix;

    public function __construct(?CacheInterface $cache = null, string $keyPrefix = 'nxp_ef_rl_')
    {
        $this->cache = $cache ?? self::createDefaultCache();
        $this->keyPrefix = $keyPrefix;
    }

	/**
	 * Enforces rate limiting for a form submission to prevent abuse by limiting
	 * the number of submissions per IP address within a specified time period.
	 *
	 * @param   int     $formId       Unique identifier of the form being submitted
	 * @param   string  $ipAddress    IP address of the submitting user
	 * @param   int     $maxRequests  Maximum number of allowed submissions
	 * @param   int     $perSeconds   Time window in seconds for rate limiting
	 *
	 * @throws SubmissionException when rate limit is exceeded
	 * @since 1.0.0
	 */
	public function enforce(int $formId, string $ipAddress, int $maxRequests, int $perSeconds): void
    {
        if ($ipAddress === '' || $maxRequests <= 0 || $perSeconds <= 0) {
            return;
        }

        $key = $this->keyPrefix . md5($formId . '|' . $ipAddress);
        $now = time();

        $record = $this->getRecord($key);

        if (!is_array($record) || !isset($record['count'], $record['expires'])) {
            $record = [
                'count' => 0,
                'expires' => $now + $perSeconds,
            ];
        }

        if ($record['expires'] <= $now) {
            $record['count'] = 0;
            $record['expires'] = $now + $perSeconds;
        }

        $record['count']++;

        $this->setRecord($key, $record, $perSeconds);

        if ($record['count'] > $maxRequests) {
            throw new SubmissionException(
                Text::_('COM_NXPEASYFORMS_RATE_LIMIT_EXCEEDED'),
                429
            );
        }
    }

	/**
	 * Gets the stored rate limiting record for the given key.
	 *
	 * @param   string  $key  Cache key for retrieving rate limit record
	 *
	 * @return array<string, mixed>|null Rate limit record containing count and expiry or null if not found
	 * @since 1.0.0
	 */
	private function getRecord(string $key): ?array
    {
        try {
            /** @var mixed $value */
            $value = $this->cache->get($key);
        } catch (InvalidArgumentException $exception) {
            return null;
        }

        return is_array($value) ? $value : null;
    }

    /**
     * Sets the rate limiting record for the given key.
     *
     * @param array<string, mixed> $record
     * @since 1.0.0
     */
    private function setRecord(string $key, array $record, int $ttl): void
    {
        try {
            $this->cache->set($key, $record, $ttl);
        } catch (InvalidArgumentException $exception) {
            // Silently ignore cache storage issues; rate limiting becomes best-effort.
        }
    }

	/**
	 * Creates and returns the default cache instance.
	 *
	 * @return CacheInterface The default cache instance configured with a filesystem adapter.
	 * @since 1.0.0
	 */
    private static function createDefaultCache(): CacheInterface
    {
        $cacheDir = defined('JPATH_CACHE')
            ? JPATH_CACHE
            : (defined('JPATH_ROOT') ? JPATH_ROOT . '/cache' : sys_get_temp_dir());

        $cacheNamespace = 'com_nxpeasyforms_rate_limiter';

        if (class_exists(FilesystemAdapter::class) && class_exists(Psr16Cache::class)) {
            $adapter = new FilesystemAdapter($cacheNamespace, 0, $cacheDir);

            return new Psr16Cache($adapter);
        }

        $storageDir = rtrim($cacheDir, DIRECTORY_SEPARATOR) . '/' . $cacheNamespace;

        return new SimpleFileCache($storageDir);
    }
}
