<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Support;

use Joomla\CMS\Factory;


use function base64_decode;
use function base64_encode;
use function hash;
use function is_string;
use function openssl_decrypt;
use function openssl_encrypt;
use function random_bytes;
use function strlen;
use function substr;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Provides encryption and decryption utilities for sensitive data storage.
 *
 * Uses AES-256-CBC encryption with application secrets for encrypting
 * sensitive configuration values.
 *
 * @since 1.0.0
 */
final class Secrets
{
    private const CIPHER = 'aes-256-cbc';

    /**
     * Encrypt a plaintext string using AES-256-CBC.
     *
     * @param string $plain The plaintext to encrypt.
     *
     * @return string Base64-encoded encrypted payload, or empty string on failure.
     * @since 1.0.0
     */
    public static function encrypt(string $plain): string
    {
        if ($plain === '') {
            return '';
        }

        $key = self::key();
        $iv = random_bytes(16);

        $encrypted = openssl_encrypt($plain, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);

        if ($encrypted === false) {
            return '';
        }

        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt an AES-256-CBC encrypted payload.
     *
     * @param string $payload Base64-encoded encrypted data.
     *
     * @return string Decrypted plaintext, or empty string on failure.
     * @since 1.0.0
     */
    public static function decrypt(string $payload): string
    {
        if ($payload === '') {
            return '';
        }

        $data = base64_decode($payload, true);

        if ($data === false || strlen($data) <= 16) {
            return '';
        }

        $iv = substr($data, 0, 16);
        $ciphertext = substr($data, 16);
        $key = self::key();

        $decrypted = openssl_decrypt($ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);

        return is_string($decrypted) ? $decrypted : '';
    }

    /**
     * Derive the encryption key from the application secret.
     *
     * @return string The SHA-256 hash of the application secret used as encryption key.
     * @since 1.0.0
     */
    private static function key(): string
    {
        $app = Factory::getApplication();
        $secret = (string) $app->get('secret');

        if ($secret === '') {
            $secret = 'nxp_easy_forms';
        }

        return hash('sha256', $secret, true);
    }
}
