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
use Joomla\Registry\Registry;
use RuntimeException;
use function array_replace_recursive;
use function call_user_func;
use function is_array;
use function is_string;
use function trim;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Encapsulates loading and persisting email-related component settings.
 */
final class EmailSettingsRepository
{
    /**
     * Retrieve email settings formatted for the administrator AJAX response.
     *
     * @param bool $includeSecrets When true, include stored secret values (used for test sends).
     *
     * @return array<string,mixed> Email settings including delivery configuration.
     */
    public function fetchSettings(bool $includeSecrets): array
    {
    $params = $this->loadComponentParams();
    $config = call_user_func(['Joomla\\CMS\\Factory', 'getConfig']);

        return [
            'from_name' => (string) $params->get('email_from_name', (string) $config->get('fromname')),
            'from_email' => (string) $params->get('email_from_address', (string) $config->get('mailfrom')),
            'recipient' => (string) $params->get('email_default_recipient', (string) $config->get('mailfrom')),
            'delivery' => $this->extractDeliverySettings($params, $includeSecrets),
        ];
    }

    /**
     * Persist email settings supplied by the administrator UI.
     *
     * @param array<string,mixed> $payload Incoming payload representing email settings.
     *
     * @return void
     */
    public function saveSettings(array $payload): void
    {
    $component = call_user_func(['Joomla\\CMS\\Component\\ComponentHelper', 'getComponent'], 'com_nxpeasyforms');
        $params = new Registry($component->params);

        $params->set('email_from_name', (string) ($payload['from_name'] ?? ''));
        $params->set('email_from_address', (string) ($payload['from_email'] ?? ''));
        $params->set('email_default_recipient', (string) ($payload['recipient'] ?? ''));

        $delivery = is_array($payload['delivery'] ?? null) ? $payload['delivery'] : [];
        $this->applyDeliverySettings($params, $delivery);

        $this->storeComponentParams((int) $component->id, $params);
    }

    /**
     * Build options structure for the component-level test email flow.
     *
     * @param string $recipient Recipient email address supplied by the administrator.
     * @param array<string,mixed> $defaults Baseline options from the form defaults helper.
     *
     * @return array<string,mixed> Options array ready for EmailService dispatch.
     */
    public function buildSettingsTestOptions(string $recipient, array $defaults): array
    {
    $component = call_user_func(['Joomla\\CMS\\Component\\ComponentHelper', 'getComponent'], 'com_nxpeasyforms');
        $params = new Registry($component->params);
    $config = call_user_func(['Joomla\\CMS\\Factory', 'getConfig']);

        $delivery = $this->extractDeliverySettings($params, true);

        return array_replace_recursive($defaults, [
            'send_email' => true,
            'email_recipient' => $recipient,
            'email_subject' => (string) $params->get('email_subject', $defaults['email_subject'] ?? ''),
            'email_from_name' => (string) $params->get('email_from_name', (string) $config->get('fromname')),
            'email_from_address' => (string) $params->get('email_from_address', (string) $config->get('mailfrom')),
            'email_reply_to' => (string) $params->get('email_reply_to', ''),
            'email_delivery' => $delivery,
        ]);
    }

    /**
     * Extract delivery/provider configuration into a client-friendly structure.
     *
     * @param Registry $params Component parameters container.
     * @param bool $includeSecrets When true, include stored secret values; otherwise mask them.
     *
     * @return array<string,mixed> Delivery configuration data.
     */
    private function extractDeliverySettings(Registry $params, bool $includeSecrets): array
    {
    $config = call_user_func(['Joomla\\CMS\\Factory', 'getConfig']);

        $valueOrMask = static function (string $value, bool $include) {
            return $include ? $value : '';
        };

        $sendgridKey = (string) $params->get('email_sendgrid_key', '');
        $smtp2goKey = (string) $params->get('email_smtp2go_key', '');
        $mailgunKey = (string) $params->get('email_mailgun_key', '');
        $postmarkToken = (string) $params->get('email_postmark_api_token', '');
        $brevoKey = (string) $params->get('email_brevo_api_key', '');
        $sesAccess = (string) $params->get('email_amazon_ses_access_key', '');
        $sesSecret = (string) $params->get('email_amazon_ses_secret_key', '');
        $smtpPassword = (string) $params->get('email_smtp_password', '');

        return [
            'provider' => (string) $params->get('email_provider', 'joomla'),
            'sendgrid' => [
                'api_key' => $valueOrMask($sendgridKey, $includeSecrets),
                'api_key_set' => $sendgridKey !== '',
            ],
            'mailgun' => [
                'api_key' => $valueOrMask($mailgunKey, $includeSecrets),
                'api_key_set' => $mailgunKey !== '',
                'domain' => (string) $params->get('email_mailgun_domain', ''),
                'region' => (string) $params->get('email_mailgun_region', 'us'),
            ],
            'postmark' => [
                'api_token' => $valueOrMask($postmarkToken, $includeSecrets),
                'api_token_set' => $postmarkToken !== '',
            ],
            'brevo' => [
                'api_key' => $valueOrMask($brevoKey, $includeSecrets),
                'api_key_set' => $brevoKey !== '',
            ],
            'amazon_ses' => [
                'access_key' => $valueOrMask($sesAccess, $includeSecrets),
                'secret_key' => $valueOrMask($sesSecret, $includeSecrets),
                'access_key_set' => $sesAccess !== '',
                'secret_key_set' => $sesSecret !== '',
                'region' => (string) $params->get('email_amazon_ses_region', 'us-east-1'),
            ],
            'mailpit' => [
                'host' => (string) $params->get('email_mailpit_host', '127.0.0.1'),
                'port' => (int) $params->get('email_mailpit_port', 1025),
            ],
            'smtp2go' => [
                'api_key' => $valueOrMask($smtp2goKey, $includeSecrets),
                'api_key_set' => $smtp2goKey !== '',
            ],
            'smtp' => [
                'host' => (string) $params->get('email_smtp_host', ''),
                'port' => (int) $params->get('email_smtp_port', 587),
                'encryption' => (string) $params->get('email_smtp_encryption', 'tls'),
                'username' => (string) $params->get('email_smtp_username', ''),
                'password' => $valueOrMask($smtpPassword, $includeSecrets),
                'password_set' => $smtpPassword !== '',
            ],
        ];
    }

    /**
     * Apply delivery configuration values into the component parameters registry.
     *
     * @param Registry $params Component params container.
     * @param array<string,mixed> $delivery Delivery configuration provided by the client.
     *
     * @return void
     */
    private function applyDeliverySettings(Registry $params, array $delivery): void
    {
        $params->set('email_provider', (string) ($delivery['provider'] ?? 'joomla'));

        if (isset($delivery['sendgrid']) && is_array($delivery['sendgrid'])) {
            $key = trim((string) ($delivery['sendgrid']['api_key'] ?? ''));

            if ($key !== '') {
                $params->set('email_sendgrid_key', $key);
            }
        }

        if (isset($delivery['smtp2go']) && is_array($delivery['smtp2go'])) {
            $key = trim((string) ($delivery['smtp2go']['api_key'] ?? ''));

            if ($key !== '') {
                $params->set('email_smtp2go_key', $key);
            }
        }

        if (isset($delivery['mailgun']) && is_array($delivery['mailgun'])) {
            $params->set('email_mailgun_domain', (string) ($delivery['mailgun']['domain'] ?? ''));
            $params->set('email_mailgun_region', (string) ($delivery['mailgun']['region'] ?? 'us'));

            $key = trim((string) ($delivery['mailgun']['api_key'] ?? ''));

            if ($key !== '') {
                $params->set('email_mailgun_key', $key);
            }
        }

        if (isset($delivery['postmark']) && is_array($delivery['postmark'])) {
            $token = trim((string) ($delivery['postmark']['api_token'] ?? ''));

            if ($token !== '') {
                $params->set('email_postmark_api_token', $token);
            }
        }

        if (isset($delivery['brevo']) && is_array($delivery['brevo'])) {
            $key = trim((string) ($delivery['brevo']['api_key'] ?? ''));

            if ($key !== '') {
                $params->set('email_brevo_api_key', $key);
            }
        }

        if (isset($delivery['amazon_ses']) && is_array($delivery['amazon_ses'])) {
            $access = trim((string) ($delivery['amazon_ses']['access_key'] ?? ''));
            $secret = trim((string) ($delivery['amazon_ses']['secret_key'] ?? ''));

            if ($access !== '') {
                $params->set('email_amazon_ses_access_key', $access);
            }

            if ($secret !== '') {
                $params->set('email_amazon_ses_secret_key', $secret);
            }

            $params->set('email_amazon_ses_region', (string) ($delivery['amazon_ses']['region'] ?? 'us-east-1'));
        }

        if (isset($delivery['mailpit']) && is_array($delivery['mailpit'])) {
            $params->set('email_mailpit_host', (string) ($delivery['mailpit']['host'] ?? '127.0.0.1'));
            $params->set('email_mailpit_port', (int) ($delivery['mailpit']['port'] ?? 1025));
        }

        if (isset($delivery['smtp']) && is_array($delivery['smtp'])) {
            $params->set('email_smtp_host', (string) ($delivery['smtp']['host'] ?? ''));
            $params->set('email_smtp_port', (int) ($delivery['smtp']['port'] ?? 587));
            $params->set('email_smtp_encryption', (string) ($delivery['smtp']['encryption'] ?? 'tls'));
            $params->set('email_smtp_username', (string) ($delivery['smtp']['username'] ?? ''));

            $password = (string) ($delivery['smtp']['password'] ?? '');

            if ($password !== '') {
                $params->set('email_smtp_password', $password);
            } elseif (empty($delivery['smtp']['password_set'])) {
                $params->set('email_smtp_password', '');
            }
        }
    }

    /**
     * Persist component parameters to the extensions table.
     *
     * @param int $extensionId Identifier of the component entry in #__extensions.
     * @param Registry $params Parameters container to serialise and persist.
     *
     * @return void
     */
    private function storeComponentParams(int $extensionId, Registry $params): void
    {
    $table = call_user_func(['Joomla\\CMS\\Table\\Table', 'getInstance'], 'extension');

        if (!$table->load($extensionId)) {
            throw new RuntimeException(Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 500);
        }

        $data = [
            'extension_id' => $extensionId,
            'params' => call_user_func([$params, 'toString']),
        ];

        if (!call_user_func([$table, 'bind'], $data) || !call_user_func([$table, 'store'])) {
            throw new RuntimeException(Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 500);
        }
    }

    /**
     * Load the component parameters, unwrapping nested registries when necessary.
     *
     * @return Registry Component parameters ready for use.
     */
    private function loadComponentParams(): Registry
    {
    $params = call_user_func(['Joomla\\CMS\\Component\\ComponentHelper', 'getParams'], 'com_nxpeasyforms');

        if ($params->exists('params')) {
            $nested = $params->get('params');

            if ($nested instanceof Registry) {
                $params = clone $nested;
            } elseif (is_array($nested)) {
                $params = new Registry($nested);
            } elseif (is_string($nested)) {
                $decoded = json_decode($nested, true);

                if (is_array($decoded)) {
                    $params = new Registry($decoded);
                }
            }
        }

        return $params;
    }
}
