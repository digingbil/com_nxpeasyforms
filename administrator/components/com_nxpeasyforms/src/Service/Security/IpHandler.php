<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
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


// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Service class responsible for detecting and anonymising client IP addresses.
 * Provides functionality to:
 * - Detect real client IP address from various HTTP headers
 * - Format IP addresses for storage based on privacy settings
 * - Anonymise IPv4 and IPv6 addresses by zeroing the last octets
 *
 * @since 1.0.0
 */
final class IpHandler
{
    /**
     * Detect client IP address.
     *
     * @param array<string, mixed>|null $server
     * @since 1.0.0
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

	/**
	 * Format an IP address for storage based on the specified privacy mode.
	 *
	 * @param   string  $ip    IP address to format
	 * @param   string  $mode  Privacy mode: 'none', 'anonymous', or 'full'
	 *
	 * @return string|null Formatted IP address or null if mode is 'none'
	 *
	 * @since 1.0.0
	 */
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

	/**
	 * Anonymizes an IP address by masking its last segment in case of IPv4
	 * or truncating its last half in case of IPv6.
	 *
	 * @param   string  $ip  The IP address to anonymize.
	 *
	 * @return string|null The anonymized IP address or null if the IP is invalid.
	 * @since 1.0.0
	 */
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
