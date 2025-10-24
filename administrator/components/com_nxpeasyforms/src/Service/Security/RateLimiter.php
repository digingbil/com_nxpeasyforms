<?php

declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Service\Security;

use Joomla\CMS\Language\Text;
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
 * PSR-16 backed rate limiter for submission throttling.
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
     * @throws SubmissionException
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
     * @return array<string, mixed>|null
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
     * @param array<string, mixed> $record
     */
    private function setRecord(string $key, array $record, int $ttl): void
    {
        try {
            $this->cache->set($key, $record, $ttl);
        } catch (InvalidArgumentException $exception) {
            // Silently ignore cache storage issues; rate limiting becomes best-effort.
        }
    }

    private static function createDefaultCache(): CacheInterface
    {
        $cacheDir = defined('JPATH_CACHE')
            ? JPATH_CACHE
            : (defined('JPATH_ROOT') ? JPATH_ROOT . '/cache' : sys_get_temp_dir());

        $adapter = new FilesystemAdapter('com_nxpeasyforms_rate_limiter', 0, $cacheDir);

        return new Psr16Cache($adapter);
    }
}
