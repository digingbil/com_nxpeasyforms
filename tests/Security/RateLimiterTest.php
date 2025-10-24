<?php

declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Tests\Security;

use Joomla\Component\Nxpeasyforms\Administrator\Service\Exception\SubmissionException;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Security\RateLimiter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

final class RateLimiterTest extends TestCase
{
    public function testAllowsWithinLimit(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $limiter = new RateLimiter($cache);

        $limiter->enforce(1, '127.0.0.1', 2, 10);
        $limiter->enforce(1, '127.0.0.1', 2, 10);

        $this->assertTrue(true);
    }

    public function testThrowsWhenExceedingLimit(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $limiter = new RateLimiter($cache);

        $this->expectException(SubmissionException::class);

        $limiter->enforce(1, '127.0.0.1', 1, 10);
        $limiter->enforce(1, '127.0.0.1', 1, 10);
    }
}
