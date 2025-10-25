<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Service\Security;

use function array_filter;
use function array_merge;
use function dns_get_record;
use function explode;
use function filter_var;
use function function_exists;
use function gethostbynamel;
use function in_array;
use function is_array;
use function parse_url;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strtolower;
use function trim;


// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Validates remote webhook endpoints to prevent SSRF.
 */
final class EndpointValidator
{
    private const ALLOWED_SCHEMES = ['http', 'https'];

    public function validate(string $endpoint): ?string
    {
        $endpoint = trim($endpoint);

        if ($endpoint === '') {
            return null;
        }

        $validated = filter_var($endpoint, FILTER_VALIDATE_URL);

        if ($validated === false) {
            return null;
        }

        $parts = parse_url($validated);
        if (!is_array($parts)) {
            return null;
        }

        $scheme = isset($parts['scheme']) ? strtolower((string) $parts['scheme']) : '';

        if (!in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            return null;
        }

        $host = $parts['host'] ?? '';
        if ($host === '') {
            return null;
        }

        $lowerHost = strtolower($host);

        if ($lowerHost === 'localhost' || str_ends_with($lowerHost, '.local')) {
            return null;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $this->isPublicIp($host) ? $validated : null;
        }

        $ips = $this->resolveHostIps($host);

        foreach ($ips as $ip) {
            if (!$this->isPublicIp($ip)) {
                return null;
            }
        }

        return $validated;
    }

    private function isPublicIp(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        if ($ip === '127.0.0.1' || $ip === '::1') {
            return false;
        }

        $public = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);

        if ($public === false) {
            return false;
        }

        if (str_contains($ip, '.')) {
            if (str_starts_with($ip, '169.254.')) {
                return false;
            }
        } else {
            $lower = strtolower($ip);
            if (str_starts_with($lower, 'fe80:') || str_starts_with($lower, 'fc') || str_starts_with($lower, 'fd')) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    private function resolveHostIps(string $host): array
    {
        $ips = [];

        if (function_exists('dns_get_record')) {
            $a = @dns_get_record($host, DNS_A);
            $aaaa = @dns_get_record($host, DNS_AAAA);

            foreach ((array) $a as $record) {
                if (!empty($record['ip'])) {
                    $ips[] = $record['ip'];
                }
            }

            foreach ((array) $aaaa as $record) {
                if (!empty($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        if (empty($ips)) {
            $fallback = @gethostbynamel($host);
            if (is_array($fallback)) {
                $ips = array_merge($ips, array_filter($fallback));
            }
        }

        return $ips;
    }
}
