<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Helper;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;


// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Provides default configuration payloads for the form builder.
 */
final class FormDefaults
{
    /**
     * Returns the baseline form configuration used to seed the Vue builder.
     *
     * @return array<string, mixed>
     * @since 1.0.0
     */
    public static function builderConfig(): array
    {
        $app = Factory::getApplication();
        $siteName = (string) $app->get('sitename');
        $mailFrom = (string) $app->get('mailfrom');

        return [
            'fields' => [],
            'options' => [
                'form_type' => 'standard',
                'store_submissions' => true,
                'send_email' => true,
                'email_recipient' => '',
                'email_subject' => Text::_('COM_NXPEASYFORMS_EMAIL_DEFAULT_SUBJECT'),
                'email_from_name' => $siteName,
                'email_from_address' => $mailFrom,
                'honeypot' => true,
                'throttle' => [
                    'max_requests' => 3,
                    'per_seconds' => 10,
                ],
                'success_message' => Text::_('COM_NXPEASYFORMS_MESSAGE_SUBMISSION_SUCCESS'),
                'error_message' => Text::_('COM_NXPEASYFORMS_ERROR_VALIDATION'),
                'ip_storage' => 'anonymous',
                'captcha' => [
                    'provider' => 'none',
                    'site_key' => '',
                    'secret_key' => '',
                ],
                'email_delivery' => [
                    'provider' => 'joomla',
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
                ],
                'webhooks' => [
                    'enabled' => false,
                    'endpoint' => '',
                    'secret' => '',
                ],
                'custom_css' => '',
                'integrations' => [
                    'joomla_article' => [
                        'enabled' => false,
                        'category_id' => 0,
                        'status' => 'unpublished',
                        'author_mode' => 'current_user',
                        'fixed_author_id' => 0,
                        'language' => '*',
                        'access' => 1,
                        'map' => [
                            'title' => '',
                            'introtext' => '',
                            'fulltext' => '',
                            'tags' => '',
                            'alias' => '',
                            'featured_image' => '',
                            'featured_image_alt' => '',
                            'featured_image_caption' => '',
                            'meta_description' => '',
                            'meta_keywords' => '',
                        ],
                    ],
                    'webhook' => [
                        'enabled' => false,
                        'endpoint' => '',
                        'secret' => '',
                    ],
                    'zapier' => [
                        'enabled' => false,
                        'webhook_url' => '',
                    ],
                    'make' => [
                        'enabled' => false,
                        'webhook_url' => '',
                    ],
                    'slack' => [
                        'enabled' => false,
                        'webhook_url' => '',
                        'message_template' => '',
                    ],
                    'teams' => [
                        'enabled' => false,
                        'webhook_url' => '',
                        'card_title' => '',
                        'message_template' => '',
                    ],
                    'mailchimp' => [
                        'enabled' => false,
                        'api_key' => '',
                        'list_id' => '',
                        'double_opt_in' => false,
                        'email_field' => '',
                        'first_name_field' => '',
                        'last_name_field' => '',
                        'tags' => [],
                    ],
                    'hubspot' => [
                        'enabled' => false,
                        'access_token' => '',
                        'portal_id' => '',
                        'form_guid' => '',
                        'email_field' => '',
                        'field_mappings' => [],
                        'legal_consent' => false,
                        'consent_text' => '',
                    ],
                    'salesforce' => [
                        'enabled' => false,
                        'org_id' => '',
                        'lead_source' => '',
                        'assignment_rule_id' => '',
                        'debug_email' => '',
                        'mappings' => [],
                    ],
                ],
            ],
        ];
    }
}
