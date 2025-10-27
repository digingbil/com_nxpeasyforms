<?php
declare(strict_types=1);

namespace Psr\SimpleCache;

if (!interface_exists(CacheInterface::class)) {
    /**
     * Minimal PSR-16 CacheInterface definition used when the psr/simple-cache package is unavailable.
     */
    interface CacheInterface
    {
        public function get($key, $default = null);

        public function set($key, $value, $ttl = null): bool;

        public function delete($key): bool;

        public function clear(): bool;

        public function getMultiple($keys, $default = null): iterable;

        public function setMultiple($values, $ttl = null): bool;

        public function deleteMultiple($keys): bool;

        public function has($key): bool;
    }
}

if (!interface_exists(InvalidArgumentException::class)) {
    /**
     * Minimal PSR-16 InvalidArgumentException definition used when psr/simple-cache is unavailable.
     */
    interface InvalidArgumentException extends \Throwable
    {
    }
}

namespace Joomla\Component\Nxpeasyforms\Administrator\Service\Cache;

use DateInterval;
use DateTimeImmutable;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

use function hash;
use function is_iterable;
use function is_string;
use function json_decode;
use function json_encode;
use function preg_match;
use function time;

use const DIRECTORY_SEPARATOR;

/**
 * Minimal filesystem-backed PSR-16 cache implementation used as a fallback when Symfony's cache
 * component is unavailable. Stores each cache entry as a JSON document on disk while respecting
 * the PSR-16 contract for TTL handling and key validation.
 *
 * @since 1.0.0
 */
final class SimpleFileCache implements CacheInterface
{
    private string $directory;

    public function __construct(string $directory)
    {
        $this->directory = rtrim($directory, DIRECTORY_SEPARATOR);
        $this->ensureDirectoryExists($this->directory);
    }

    public function get($key, $default = null)
    {
        $this->assertValidKey($key);

        $file = $this->pathForKey($key);

        if (!is_file($file)) {
            return $default;
        }

        $payload = $this->readPayload($file);

        if ($payload === null) {
            return $default;
        }

        if ($payload['expires'] !== null && $payload['expires'] <= time()) {
            File::delete($file);

            return $default;
        }

        return $payload['value'];
    }

    public function set($key, $value, $ttl = null): bool
    {
        $this->assertValidKey($key);

        $expires = $this->resolveExpiration($ttl);

        if ($expires !== null && $expires <= time()) {
            return $this->delete($key);
        }

        $file = $this->pathForKey($key);
        $this->ensureDirectoryExists($this->directory);

        $payload = json_encode(['value' => $value, 'expires' => $expires]);

        if ($payload === false) {
            return false;
        }

        return file_put_contents($file, $payload, LOCK_EX) !== false;
    }

    public function delete($key): bool
    {
        $this->assertValidKey($key);

        $file = $this->pathForKey($key);

        if (!is_file($file)) {
            return true;
        }

        return File::delete($file);
    }

    public function clear(): bool
    {
        if (!is_dir($this->directory)) {
            return true;
        }

        $iterator = new \FilesystemIterator($this->directory);

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                File::delete($file->getPathname());
            }
        }

        return true;
    }

    public function getMultiple($keys, $default = null): iterable
    {
        if (!is_iterable($keys)) {
            throw new SimpleCacheInvalidArgumentException('Cache keys must be iterable.');
        }

        $results = [];

        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $default);
        }

        return $results;
    }

    public function setMultiple($values, $ttl = null): bool
    {
        if (!is_iterable($values)) {
            throw new SimpleCacheInvalidArgumentException('Cache values must be iterable.');
        }

        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                return false;
            }
        }

        return true;
    }

    public function deleteMultiple($keys): bool
    {
        if (!is_iterable($keys)) {
            throw new SimpleCacheInvalidArgumentException('Cache keys must be iterable.');
        }

        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                return false;
            }
        }

        return true;
    }

    public function has($key): bool
    {
        $this->assertValidKey($key);

        $file = $this->pathForKey($key);

        if (!is_file($file)) {
            return false;
        }

        $payload = $this->readPayload($file);

        if ($payload === null) {
            return false;
        }

        if ($payload['expires'] !== null && $payload['expires'] <= time()) {
            File::delete($file);

            return false;
        }

        return true;
    }

    private function assertValidKey($key): void
    {
        if (!is_string($key) || $key === '' || preg_match('#[{}()/\\\\@:]#', $key)) {
            throw new SimpleCacheInvalidArgumentException('Invalid cache key provided.');
        }
    }

    private function pathForKey(string $key): string
    {
        $hash = hash('sha256', $key);

        return $this->directory . '/' . $hash . '.json';
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        Folder::create($directory);
    }

    /**
     * @return array{value:mixed,expires:int|null}|null
     */
    private function readPayload(string $file): ?array
    {
        $contents = file_get_contents($file);

        if ($contents === false || $contents === '') {
            return null;
        }

        /** @var mixed $decoded */
        $decoded = json_decode($contents, true);

        if (!is_array($decoded) || !array_key_exists('value', $decoded)) {
            return null;
        }

        $expires = $decoded['expires'] ?? null;

        if ($expires !== null && !is_int($expires)) {
            $expires = null;
        }

        return [
            'value' => $decoded['value'],
            'expires' => $expires,
        ];
    }

    /**
     * Converts a TTL representation to an absolute UNIX timestamp.
     *
     * @param  int|DateInterval|null  $ttl  TTL value in seconds or DateInterval.
     *
     * @throws SimpleCacheInvalidArgumentException When TTL format is invalid.
     */
    private function resolveExpiration($ttl): ?int
    {
        if ($ttl === null) {
            return null;
        }

        if ($ttl instanceof DateInterval) {
            $now = new DateTimeImmutable();

            return $now->add($ttl)->getTimestamp();
        }

        if (is_int($ttl)) {
            return $ttl > 0 ? time() + $ttl : time();
        }

        throw new SimpleCacheInvalidArgumentException('TTL must be null, integer seconds, or a DateInterval.');
    }
}

/**
 * @internal
 */
final class SimpleCacheInvalidArgumentException extends \InvalidArgumentException implements InvalidArgumentException
{
}
