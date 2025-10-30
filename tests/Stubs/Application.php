<?php

declare(strict_types=1);

namespace Joomla\CMS\Application;

/**
 * Minimal stub of Joomla CMSApplicationInterface for tests.
 */
interface CMSApplicationInterface
{
    /** @return bool true on success */
    public function login(array $credentials, array $options = []);

    /** @return void */
    public function triggerEvent(string $name, array $args = []);

    /** @return object|null */
    public function getIdentity();
}
