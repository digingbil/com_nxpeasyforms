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

final class Secrets
{
    private const CIPHER = 'aes-256-cbc';

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
