<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Ajax\Support;

use Joomla\CMS\Language\Text;
use Joomla\Component\Nxpeasyforms\Administrator\Support\CaptchaOptions;
use Joomla\Component\Nxpeasyforms\Administrator\Support\Sanitizer;
use Joomla\Component\Nxpeasyforms\Administrator\Support\Secrets;
use function array_key_exists;
use function array_merge;
use function is_array;
use function is_numeric;
use function preg_match;
use function strtolower;
use function trim;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Normalises form options between storage and client payload representations.
 */
final class FormOptionsNormalizer
{
    /**
     * Normalise options for persistence in the database.
     *
     * @param array<string,mixed> $options Options provided by the client payload.
     * @param array<string,mixed> $existingOptions Previously stored options used to preserve secrets.
     *
     * @return array<string,mixed> Normalised options ready for storage.
     */
    public function normalizeForStorage(array $options, array $existingOptions = []): array
    {
        $options['email_delivery'] = $this->normalizeEmailDelivery(
            is_array($options['email_delivery'] ?? null) ? $options['email_delivery'] : []
        );

        $existingCaptcha = is_array($existingOptions['captcha'] ?? null) ? $existingOptions['captcha'] : [];

        $options['captcha'] = CaptchaOptions::normalizeForStorage(
            is_array($options['captcha'] ?? null) ? $options['captcha'] : [],
            $existingCaptcha
        );

        $integrations = is_array($options['integrations'] ?? null) ? $options['integrations'] : [];
        $existingIntegrations = is_array($existingOptions['integrations'] ?? null)
            ? $existingOptions['integrations']
            : [];

        $options['integrations'] = $this->normalizeIntegrations($integrations, $existingIntegrations);

        return $options;
    }

    /**
     * Normalise stored options so they can be safely delivered back to the client.
     *
     * @param array<string,mixed> $options Options loaded from the database.
     *
     * @return array<string,mixed> Normalised options safe for client consumption.
     */
    public function normalizeForClient(array $options): array
    {
        $normalized = $this->normalizeForStorage($options, $options);
        $normalized['captcha'] = CaptchaOptions::normalizeForClient($normalized['captcha'] ?? []);

        if (isset($normalized['integrations']['mailchimp']) && is_array($normalized['integrations']['mailchimp'])) {
            $normalized['integrations']['mailchimp']['api_key'] = '';
            $normalized['integrations']['mailchimp']['remove_api_key'] = false;
        }

        return $normalized;
    }

    /**
     * Ensure the email delivery configuration contains expected keys and defaults.
     *
     * @param array<string,mixed> $delivery Delivery configuration provided by the client.
     *
     * @return array<string,mixed> Delivery configuration with defaults applied.
     */
    private function normalizeEmailDelivery(array $delivery): array
    {
        $provider = isset($delivery['provider']) ? (string) $delivery['provider'] : 'joomla';
        $delivery['provider'] = $provider ?: 'joomla';

        $defaults = [
            'sendgrid' => [
                'api_key' => '',
            ],
            'mailgun' => [
                'api_key' => '',
                'domain' => '',
                'region' => 'us',
            ],
            'postmark' => [
                'api_token' => '',
            ],
            'brevo' => [
                'api_key' => '',
            ],
            'amazon_ses' => [
                'access_key' => '',
                'secret_key' => '',
                'region' => 'us-east-1',
            ],
            'mailpit' => [
                'host' => '127.0.0.1',
                'port' => 1025,
            ],
            'smtp2go' => [
                'api_key' => '',
            ],
            'smtp' => [
                'host' => '',
                'port' => 587,
                'encryption' => 'tls',
                'username' => '',
                'password' => '',
                'password_set' => false,
            ],
        ];

        foreach ($defaults as $key => $values) {
            $delivery[$key] = isset($delivery[$key]) && is_array($delivery[$key]) ? $delivery[$key] : [];

            foreach ($values as $field => $defaultValue) {
                if (!array_key_exists($field, $delivery[$key]) || $delivery[$key][$field] === null) {
                    $delivery[$key][$field] = $defaultValue;
                    continue;
                }

                if (is_string($delivery[$key][$field])) {
                    $delivery[$key][$field] = trim($delivery[$key][$field]);
                }

                if ($field === 'port') {
                    $delivery[$key][$field] = (int) $delivery[$key][$field] ?: $defaultValue;
                }
            }

            if ($key === 'mailgun') {
                $delivery[$key]['region'] = strtolower($delivery[$key]['region'] ?: 'us');
            }

            if ($key === 'amazon_ses') {
                $delivery[$key]['region'] = strtolower($delivery[$key]['region'] ?: 'us-east-1');
            }

            if ($key === 'mailpit') {
                $delivery[$key]['port'] = (int) ($delivery[$key]['port'] ?? 1025) ?: 1025;
            }

            if ($key === 'smtp') {
                $delivery[$key]['port'] = (int) ($delivery[$key]['port'] ?? 587) ?: 587;
                $delivery[$key]['password_set'] = !empty($delivery[$key]['password_set']);
            }
        }

        return $delivery;
    }

    /**
     * Normalise integrations configuration and remove unsupported integrations.
     *
     * @param array<string,mixed> $integrations Integrations configuration provided by the client.
     * @param array<string,mixed> $existing Previously stored integration settings for secret preservation.
     *
     * @return array<string,mixed> Normalised integrations configuration.
     */
    private function normalizeIntegrations(array $integrations, array $existing = []): array
    {
        if (isset($integrations['joomla_article']) && is_array($integrations['joomla_article'])) {
            $integrations['joomla_article'] = $this->normalizeArticleIntegration($integrations['joomla_article']);
        }

        if (isset($integrations['mailchimp']) && is_array($integrations['mailchimp'])) {
            $existingMailchimp = is_array($existing['mailchimp'] ?? null) ? $existing['mailchimp'] : [];
            $integrations['mailchimp'] = $this->normalizeMailchimpIntegration($integrations['mailchimp'], $existingMailchimp);
        }

        unset($integrations['woocommerce']);

        return $integrations;
    }

    /**
     * Normalise Mailchimp integration settings while preserving or encrypting secrets.
     *
     * @param array<string,mixed> $settings Incoming settings from the client payload.
     * @param array<string,mixed> $existing Previously stored Mailchimp settings.
     *
     * @return array<string,mixed> Normalised Mailchimp configuration ready for storage.
     */
    private function normalizeMailchimpIntegration(array $settings, array $existing): array
    {
        $normalized = [
            'enabled' => !empty($settings['enabled']),
            'list_id' => trim((string) ($settings['list_id'] ?? '')),
            'double_opt_in' => !empty($settings['double_opt_in']),
            'email_field' => trim((string) ($settings['email_field'] ?? '')),
            'first_name_field' => trim((string) ($settings['first_name_field'] ?? '')),
            'last_name_field' => trim((string) ($settings['last_name_field'] ?? '')),
            'tags' => [],
            'api_key' => '',
            'api_key_set' => false,
            'remove_api_key' => false,
        ];

        $tags = $settings['tags'] ?? [];

        if (is_array($tags)) {
            foreach ($tags as $tag) {
                if (!is_string($tag)) {
                    continue;
                }

                $clean = Sanitizer::cleanText($tag);

                if ($clean !== '') {
                    $normalized['tags'][] = $clean;
                }
            }
        }

        $removeKey = !empty($settings['remove_api_key']);
        $providedKey = trim((string) ($settings['api_key'] ?? ''));

        if ($removeKey) {
            return $normalized;
        }

        if ($providedKey !== '') {
            $encrypted = Secrets::encrypt($providedKey);

            if ($encrypted === '') {
                throw new \RuntimeException(Text::_('COM_NXPEASYFORMS_MAILCHIMP_ENCRYPTION_FAILED'), 500);
            }

            $normalized['api_key'] = $encrypted;
            $normalized['api_key_set'] = true;

            return $normalized;
        }

        $existingKey = trim((string) ($existing['api_key'] ?? ''));

        if ($existingKey === '') {
            return $normalized;
        }

        $decrypted = Secrets::decrypt($existingKey);

        if ($decrypted !== '') {
            $normalized['api_key'] = $existingKey;
            $normalized['api_key_set'] = true;

            return $normalized;
        }

        if ($this->isMailchimpApiKey($existingKey)) {
            $encrypted = Secrets::encrypt($existingKey);

            if ($encrypted === '') {
                throw new \RuntimeException(Text::_('COM_NXPEASYFORMS_MAILCHIMP_ENCRYPTION_FAILED'), 500);
            }

            $normalized['api_key'] = $encrypted;
            $normalized['api_key_set'] = true;
        }

        return $normalized;
    }

    /**
     * Convert legacy article integration settings into the expected storage shape.
     *
     * @param array<string,mixed> $settings Legacy integration settings.
     *
     * @return array<string,mixed> Normalised Joomla article integration configuration.
     */
    private function normalizeArticleIntegration(array $settings): array
    {
        $map = is_array($settings['map'] ?? null) ? $settings['map'] : [];
        $map = array_merge(
            [
                'title' => '',
                'introtext' => '',
                'fulltext' => '',
                'tags' => '',
                'alias' => '',
                'featured_image' => '',
                'featured_image_alt' => '',
                'featured_image_caption' => '',
            ],
            $map
        );

        if (($map['featured_image'] ?? '') !== '' && !isset($map['media'])) {
            $map['media'] = [
                'featured_image' => $map['featured_image'],
            ];
        }

        $converted = [
            'enabled' => !empty($settings['enabled']),
            'category_id' => $this->parseCategoryId($settings),
            'status' => (string) ($settings['post_status'] ?? 'unpublished'),
            'author_mode' => (string) ($settings['author_mode'] ?? 'current_user'),
            'fixed_author_id' => (int) ($settings['fixed_author_id'] ?? 0),
            'language' => (string) ($settings['language'] ?? '*'),
            'access' => (int) ($settings['access'] ?? 1),
            'map' => $map,
        ];

        $tagsField = $this->extractLegacyTagsField($settings);

        if ($tagsField !== '') {
            $converted['map']['tags'] = $tagsField;
        }

        return $converted;
    }

    /**
     * Parse and return an integer category identifier from integration settings.
     *
     * @param array<string,mixed> $settings Integration settings array.
     *
     * @return int Resolved category identifier or zero when unavailable.
     */
    private function parseCategoryId(array $settings): int
    {
        if (isset($settings['category_id'])) {
            return (int) $settings['category_id'];
        }

        $postType = $settings['post_type'] ?? '';

        if (is_numeric($postType)) {
            return (int) $postType;
        }

        return 0;
    }

    /**
     * Extract legacy tag mapping information from historical payload structures.
     *
     * @param array<string,mixed> $settings Legacy settings array containing potential taxonomy mappings.
     *
     * @return string Field name mapped to tags or an empty string when none exist.
     */
    private function extractLegacyTagsField(array $settings): string
    {
        $legacyTaxonomies = is_array($settings['taxonomies'] ?? null) ? $settings['taxonomies'] : [];

        foreach ($legacyTaxonomies as $taxonomy) {
            if (!is_array($taxonomy)) {
                continue;
            }

            $name = (string) ($taxonomy['taxonomy'] ?? '');

            if ($name !== 'post_tag' && $name !== 'tags') {
                continue;
            }

            $field = (string) ($taxonomy['field'] ?? '');

            if ($field !== '') {
                return $field;
            }
        }

        return '';
    }

    /**
     * Determine whether a candidate string resembles a Mailchimp API key.
     *
     * @param string $value Candidate value to inspect.
     *
     * @return bool True when the string matches the expected Mailchimp key pattern.
     */
    private function isMailchimpApiKey(string $value): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9]{10,}-[a-z0-9]+$/', $value);
    }
}
