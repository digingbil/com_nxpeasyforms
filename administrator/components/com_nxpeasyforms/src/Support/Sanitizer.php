<?php

declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Support;

use Joomla\CMS\Filter\InputFilter;

/**
 * Provides sanitisation helpers mirroring WordPress' sanitize_* functions.
 */
final class Sanitizer
{
    private static ?InputFilter $filter = null;

    private function __construct()
    {
    }

    public static function cleanText($value): string
    {
        if (!is_string($value)) {
            $value = is_scalar($value) ? (string) $value : '';
        }

        $trimmed = trim($value);
        $filtered = self::filter()->clean($trimmed, 'STRING');

        return self::normaliseWhitespace($filtered);
    }

    public static function cleanTextarea($value): string
    {
        if (!is_string($value)) {
            $value = is_scalar($value) ? (string) $value : '';
        }

        $normalised = str_replace(["\r\n", "\r"], "\n", trim($value));
        $filtered = self::filter()->clean($normalised, 'STRING');

        return self::preserveLineBreaks($filtered);
    }

    public static function cleanEmail(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $sanitised = filter_var(trim($value), FILTER_SANITIZE_EMAIL);

        return is_string($sanitised) ? $sanitised : '';
    }

    public static function isValidEmail(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * @param array<int|string, mixed> $values
     *
     * @return array<int|string, string>
     */
    public static function cleanTextArray(array $values): array
    {
        $sanitised = [];

        foreach ($values as $key => $value) {
            $sanitised[$key] = self::cleanText($value);
        }

        return $sanitised;
    }

    private static function filter(): InputFilter
    {
        if (self::$filter === null) {
            self::$filter = InputFilter::getInstance();
        }

        return self::$filter;
    }

    private static function normaliseWhitespace(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', $value);

        return $value !== null ? trim($value) : '';
    }

    private static function preserveLineBreaks(string $value): string
    {
        $value = preg_replace("/[ \t]+/", ' ', $value);

        return $value !== null ? trim($value) : '';
    }
}
