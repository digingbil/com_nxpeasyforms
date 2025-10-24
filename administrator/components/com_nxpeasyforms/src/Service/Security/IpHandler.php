<?php

declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Service\Security;

use function count;
use function explode;
use function filter_var;
use function inet_ntop;
use function inet_pton;
use function str_repeat;
use function substr;
use function trim;

/**
 * Detects and anonymises client IP addresses.
 */
final class IpHandler
{
    /**
     * Detect client IP address.
     *
     * @param array<string, mixed>|null $server
     */
    public function detect(?array $server = null): string
    {
        $server = $server ?? $_SERVER;

        $keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];

        foreach ($keys as $key) {
            if (empty($server[$key])) {
                continue;
            }

            $value = explode(',', (string) $server[$key]);
            $ip = trim($value[0]);

            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return '';
    }

    public function formatForStorage(string $ip, string $mode): ?string
    {
        if ($ip === '') {
            return null;
        }

        switch ($mode) {
            case 'none':
                return null;

            case 'anonymous':
                return $this->anonymise($ip);

            case 'full':
            default:
                return $ip;
        }
    }

    public function anonymise(string $ip): ?string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $segments = explode('.', $ip);
            $segments[count($segments) - 1] = '0';

            return implode('.', $segments);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = @inet_pton($ip);

            if ($packed === false) {
                return null;
            }

            $prefix = substr($packed, 0, 8);
            $anonymised = $prefix . str_repeat("\0", 8);
            $expanded = @inet_ntop($anonymised);

            return $expanded ?: null;
        }

        return null;
    }
}
