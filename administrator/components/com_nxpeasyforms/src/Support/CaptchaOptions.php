<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Support;

use function array_key_exists;
use function base64_decode;
use function in_array;
use function is_array;
use function is_string;
use function strlen;
use function trim;

/**
 * Helper utilities for normalising CAPTCHA configuration payloads.
 *
 * Handles default structures, encrypted secret management, legacy payload
 * migration, and sanitisation for both storage and client consumption.
 *
 * @since 1.0.0
 */
final class CaptchaOptions
{
    private const PROVIDERS = ['recaptcha_v3', 'turnstile', 'friendlycaptcha'];

    /**
     * Returns the default captcha configuration payload.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        $defaults = ['provider' => 'none'];

        foreach (self::PROVIDERS as $provider) {
            $defaults[$provider] = [
                'site_key' => '',
                'secret_key' => '',
                'secret_key_set' => false,
            ];
        }

        return $defaults;
    }

    /**
     * Normalise captcha options for persistent storage.
     *
     * @param array<string, mixed> $incoming Incoming request payload.
     * @param array<string, mixed> $existing Previously stored configuration.
     *
     * @return array<string, mixed>
     */
    public static function normalizeForStorage(array $incoming, array $existing = []): array
    {
        $incoming = self::migrateLegacyShape($incoming);
        $existing = self::mergeWithDefaults($existing);

        $provider = isset($incoming['provider']) && is_string($incoming['provider'])
            ? trim($incoming['provider'])
            : ($existing['provider'] ?? 'none');

        if ($provider === '' || !in_array($provider, self::PROVIDERS, true)) {
            $provider = 'none';
        }

        $payload = ['provider' => $provider];

        foreach (self::PROVIDERS as $name) {
            $incomingConfig = is_array($incoming[$name] ?? null) ? $incoming[$name] : [];
            $existingConfig = is_array($existing[$name] ?? null) ? $existing[$name] : [];

            $payload[$name] = self::normalizeProvider($incomingConfig, $existingConfig);
        }

        return $payload;
    }

    /**
     * Prepare captcha options for client delivery by redacting stored secrets.
     *
     * @param array<string, mixed> $stored Stored captcha payload.
     *
     * @return array<string, mixed>
     */
    public static function normalizeForClient(array $stored): array
    {
        $stored = self::mergeWithDefaults($stored);
        $payload = ['provider' => $stored['provider'] ?? 'none'];

        foreach (self::PROVIDERS as $name) {
            $config = is_array($stored[$name] ?? null) ? $stored[$name] : [];
            $secret = is_string($config['secret_key'] ?? null) ? $config['secret_key'] : '';
            $secretSet = array_key_exists('secret_key_set', $config)
                ? (bool) $config['secret_key_set']
                : ($secret !== '');

            $payload[$name] = [
                'site_key' => is_string($config['site_key'] ?? null) ? $config['site_key'] : '',
                'secret_key' => '',
                'secret_key_set' => $secretSet,
            ];
        }

        return $payload;
    }

    /**
     * Resolve and decrypt a stored secret key value.
     *
     * @param string $value Stored secret key (encrypted or legacy plain text).
     *
     * @return string Plain text secret.
     */
    public static function decryptSecret(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $decrypted = Secrets::decrypt($value);

        return $decrypted !== '' ? $decrypted : $value;
    }

    /**
     * Normalise a provider configuration for storage.
     *
     * @param array<string, mixed> $incoming Provider payload from request.
     * @param array<string, mixed> $existing Provider payload from storage.
     *
     * @return array<string, mixed>
     */
    private static function normalizeProvider(array $incoming, array $existing): array
    {
        $siteKey = isset($incoming['site_key']) && is_string($incoming['site_key'])
            ? trim($incoming['site_key'])
            : (is_string($existing['site_key'] ?? null) ? trim((string) $existing['site_key']) : '');

        $existingSecret = is_string($existing['secret_key'] ?? null) ? $existing['secret_key'] : '';
        $incomingSecret = isset($incoming['secret_key']) && is_string($incoming['secret_key'])
            ? trim($incoming['secret_key'])
            : '';

        $removeSecret = !empty($incoming['remove_secret']);
        $incomingSecretSet = array_key_exists('secret_key_set', $incoming)
            ? (bool) $incoming['secret_key_set']
            : null;

        if ($removeSecret) {
            return [
                'site_key' => $siteKey,
                'secret_key' => '',
                'secret_key_set' => false,
            ];
        }

        if ($incomingSecret !== '' && $incomingSecret === $existingSecret) {
            // No change requested; keep existing value.
            $secret = self::ensureEncrypted($existingSecret);

            return [
                'site_key' => $siteKey,
                'secret_key' => $secret,
                'secret_key_set' => $secret !== '',
            ];
        }

        if ($incomingSecret !== '') {
            $encrypted = Secrets::encrypt($incomingSecret);

            if ($encrypted === '') {
                // Encryption failed; keep prior value if available.
                $encrypted = self::ensureEncrypted($existingSecret);
            }

            return [
                'site_key' => $siteKey,
                'secret_key' => $encrypted,
                'secret_key_set' => $encrypted !== '',
            ];
        }

        if ($incomingSecretSet === true || ($incomingSecretSet === null && $existingSecret !== '')) {
            $secret = self::ensureEncrypted($existingSecret);

            return [
                'site_key' => $siteKey,
                'secret_key' => $secret,
                'secret_key_set' => $secret !== '',
            ];
        }

        return [
            'site_key' => $siteKey,
            'secret_key' => '',
            'secret_key_set' => false,
        ];
    }

    /**
     * Merge stored payload with defaults to guarantee structure.
     *
     * @param array<string, mixed> $options Stored payload.
     *
     * @return array<string, mixed>
     */
    private static function mergeWithDefaults(array $options): array
    {
        $defaults = self::defaults();
        $provider = isset($options['provider']) && is_string($options['provider'])
            ? trim($options['provider'])
            : 'none';

        if ($provider === '' || !in_array($provider, self::PROVIDERS, true)) {
            $provider = 'none';
        }

        $defaults['provider'] = $provider;

        foreach (self::PROVIDERS as $name) {
            $source = is_array($options[$name] ?? null) ? $options[$name] : [];

            $defaults[$name]['site_key'] = is_string($source['site_key'] ?? null)
                ? trim($source['site_key'])
                : '';
            $defaults[$name]['secret_key'] = is_string($source['secret_key'] ?? null)
                ? $source['secret_key']
                : '';
            $defaults[$name]['secret_key_set'] = array_key_exists('secret_key_set', $source)
                ? (bool) $source['secret_key_set']
                : ($defaults[$name]['secret_key'] !== '');
        }

        return $defaults;
    }

    /**
     * Convert legacy payloads (flat site/secret pairs) into provider buckets.
     *
     * @param array<string, mixed> $payload Incoming payload.
     *
     * @return array<string, mixed>
     */
    private static function migrateLegacyShape(array $payload): array
    {
        if (!isset($payload['site_key']) && !isset($payload['secret_key'])) {
            return $payload;
        }

        $provider = isset($payload['provider']) && is_string($payload['provider'])
            ? trim($payload['provider'])
            : '';

        if (!in_array($provider, self::PROVIDERS, true)) {
            return $payload;
        }

        $payload[$provider] = array_merge(
            is_array($payload[$provider] ?? null) ? $payload[$provider] : [],
            [
                'site_key' => is_string($payload['site_key'] ?? null) ? $payload['site_key'] : '',
                'secret_key' => is_string($payload['secret_key'] ?? null) ? $payload['secret_key'] : '',
            ]
        );

        unset($payload['site_key'], $payload['secret_key']);

        return $payload;
    }

    /**
     * Ensure a stored secret value is encrypted; migrate plaintext when necessary.
     *
     * @param string $value Existing stored value (possibly plaintext).
     *
     * @return string Encrypted value or empty string.
     */
    private static function ensureEncrypted(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (self::isEncrypted($value)) {
            return $value;
        }

        $encrypted = Secrets::encrypt($value);

        return $encrypted !== '' ? $encrypted : $value;
    }

    /**
     * Determine whether a value appears to be an encrypted payload.
     *
     * @param string $value Stored value.
     */
    private static function isEncrypted(string $value): bool
    {
        $decoded = base64_decode($value, true);

        if ($decoded === false) {
            return false;
        }

        return strlen($decoded) > 16;
    }
}
