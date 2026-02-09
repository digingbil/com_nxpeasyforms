<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 *
 * PSR-16 SimpleCache CacheInterface polyfill.
 * This interface is only loaded if psr/simple-cache package is not installed.
 */

namespace Psr\SimpleCache;

/**
 * Minimal PSR-16 CacheInterface definition.
 *
 * @since 1.0.0
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
