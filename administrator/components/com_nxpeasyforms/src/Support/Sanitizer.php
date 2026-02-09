<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Support;

use Joomla\CMS\Filter\InputFilter;


// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Provides sanitization helpers consistent with Joomla input filtering conventions.
 *
 * Offers static methods for cleaning and validating text inputs, textareas,
 * and email addresses according to Joomla standards.
 *
 * @since 1.0.0
 */
final class Sanitizer
{
    private static ?InputFilter $filter = null;

    /**
     * Constructor (private).
     *
     * @since 1.0.0
     */
    private function __construct()
    {
    }

    /**
     * Clean a scalar value as plain text.
     *
     * Converts the value to a string, trims whitespace and normalizes spacing
     * using Joomla's input filter.
     *
     * @param mixed $value The value to clean.
     *
     * @return string Cleaned text value.
     * @since 1.0.0
     */
    public static function cleanText($value): string
    {
        if (!is_string($value)) {
            $value = is_scalar($value) ? (string) $value : '';
        }

        $trimmed = trim($value);
        $filtered = self::filter()->clean($trimmed, 'STRING');

        return self::normalizeWhitespace($filtered);
    }

    /**
     * Clean a scalar value as textarea text.
     *
     * Converts the value to a string, normalizes line endings and whitespace
     * while preserving line breaks.
     *
     * @param mixed $value The value to clean.
     *
     * @return string Cleaned textarea value with preserved line breaks.
     * @since 1.0.0
     */
    public static function cleanTextarea($value): string
    {
        if (!is_string($value)) {
            $value = is_scalar($value) ? (string) $value : '';
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", trim($value));
        $filtered = self::filter()->clean($normalized, 'STRING');

        return self::preserveLineBreaks($filtered);
    }

    /**
     * Clean and sanitize an email address.
     *
     * @param string|null $value The email value to sanitize.
     *
     * @return string Sanitized email address, or empty string if invalid/null.
     * @since 1.0.0
     */
    public static function cleanEmail(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $sanitised = filter_var(trim($value), FILTER_SANITIZE_EMAIL);

        return is_string($sanitised) ? $sanitised : '';
    }

    /**
     * Validate an email address.
     *
     * @param string|null $value The email address to validate.
     *
     * @return bool True if the value is a valid email address.
     * @since 1.0.0
     */
    public static function isValidEmail(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Clean an array of text values.
     *
     * @param array<int|string,mixed> $values The array of values to clean.
     *
     * @return array<int|string,string> Array with cleaned text values.
     * @since 1.0.0
     */
    public static function cleanTextArray(array $values): array
    {

	    return array_map(function ($value) {
		    return self::cleanText($value);
	    }, $values);
    }

    /**
     * Get or create the singleton input filter instance.
     *
     * @return InputFilter
     * @since 1.0.0
     */
    private static function filter(): InputFilter
    {
        if (self::$filter === null) {
            self::$filter = InputFilter::getInstance();
        }

        return self::$filter;
    }

    /**
     * Normalize whitespace in a string.
     * E.g.: "  foo bar  " => "foo bar"
     *
     * @param string $value The string to normalize.
     *
     * @return string String with normalized whitespace.
     * @since 1.0.0
     */
    private static function normalizeWhitespace(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', $value);

        return $value !== null ? trim($value) : '';
    }

    /**
     * Preserve line breaks in a string while normalizing other whitespace.
     *
     * @param string $value The string to process.
     *
     * @return string String with preserved line breaks and normalized spacing.
     * @since 1.0.0
     */
    private static function preserveLineBreaks(string $value): string
    {
        $value = preg_replace("/[ \t]+/", ' ', $value);

        return $value !== null ? trim($value) : '';
    }
}
