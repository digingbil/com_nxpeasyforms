<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Service\Email;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Mail\MailerFactory;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\Mail\MailerInterface;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations\HttpClient;
use Joomla\Component\Nxpeasyforms\Administrator\Support\Secrets;
use Joomla\Registry\Registry;


use function array_filter;
use function array_key_exists;
use function array_map;
use function array_values;
use function base64_encode;
use function explode;
use function json_decode;
use function filter_var;
use function htmlspecialchars;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function nl2br;
use function sprintf;
use function strtolower;
use function trim;

use const FILTER_VALIDATE_EMAIL;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Email service for managing form submission notifications.
 *
 * Handles sending email notifications for form submissions using configurable
 * email delivery providers (Joomla native, SendGrid, Mailgun, SMTP, etc.).
 *
 * @since 1.0.0
 */
final class EmailService
{
    private MailerInterface $mailer;

    private MailerFactoryInterface $mailerFactory;

    private Registry $componentParams;

    private HttpClient $httpClient;

    /**
     * Constructor.
     *
     * @param \Joomla\CMS\Mail\MailerInterface|null $mailer Optional mailer instance.
     * @param \Joomla\Registry\Registry|null $componentParams Optional component params registry.
     * @param HttpClient|null $httpClient Optional HTTP client for external API calls.
     * @param MailerFactoryInterface|null $mailerFactory Optional mailer factory for SMTP transports.
     *
     * @since 1.0.0
     */
    public function __construct(
        ?MailerInterface $mailer = null,
        ?Registry $componentParams = null,
        ?HttpClient $httpClient = null,
        ?MailerFactoryInterface $mailerFactory = null
    ) {
        $container = Factory::getContainer();

        $app = Factory::getApplication();

        if ($mailer instanceof MailerInterface) {
            $this->mailer = $mailer;
        } elseif ($container->has(MailerInterface::class)) {
            /** @var MailerInterface $mailerInstance */
            $mailerInstance = $container->get(MailerInterface::class);
            $this->mailer = $mailerInstance;
        } else {
            $this->mailer = Factory::getMailer();
        }

        if ($mailerFactory instanceof MailerFactoryInterface) {
            $this->mailerFactory = $mailerFactory;
        } elseif ($container->has(MailerFactoryInterface::class)) {
            /** @var MailerFactoryInterface $resolvedFactory */
            $resolvedFactory = $container->get(MailerFactoryInterface::class);
            $this->mailerFactory = $resolvedFactory;
        } else {
            $configuration = method_exists($app, 'getConfig')
                ? $app->getConfig()
                : new Registry();
            $this->mailerFactory = new MailerFactory($configuration);
        }

        $resolvedParams = $componentParams;

        if ($resolvedParams instanceof Registry) {
            $resolvedParams = clone $resolvedParams;
        } elseif ($resolvedParams === null) {
            $resolvedParams = ComponentHelper::getParams('com_nxpeasyforms');
        } else {
            $resolvedParams = new Registry((array) $resolvedParams);
        }

        $nested = $resolvedParams instanceof Registry ? $resolvedParams->get('params') : null;

        if ($nested instanceof Registry) {
            $resolvedParams = clone $nested;
        } elseif (\is_array($nested)) {
            $resolvedParams = new Registry($nested);
        } elseif (is_string($nested) && $nested !== '') {
            $decoded = json_decode($nested, true);

            if (\is_array($decoded)) {
                $resolvedParams = new Registry($decoded);
            }
        }

        $this->componentParams = $resolvedParams;

        $this->httpClient = $httpClient ?? new HttpClient();
    }

    /**
     * Dispatch a submission for email notification.
     *
     * Processes the given form submission and sends email notifications to configured
     * recipients using the selected email delivery provider.
     *
     * @param array<string,mixed> $form The form definition array with config/options.
     * @param array<string,mixed> $submission The submission data payload.
     * @param array<string,mixed> $context Additional context (field metadata, etc).
     *
     * @return array{sent:bool,message:string,error?:string|null} Result with sent flag and message.
     * @since 1.0.0
     */
    public function dispatchSubmission(array $form, array $submission, array $context = []): array
    {
        $options = is_array($form['config']['options'] ?? null)
            ? $form['config']['options']
            : [];

        $config = $this->mergeWithDefaults($options);

        if (!$config['send_email']) {
            return [
                'sent' => false,
                'message' => Text::_('COM_NXPEASYFORMS_EMAIL_DISABLED'),
                'error' => null,
            ];
        }

        $recipients = $this->resolveRecipients($config['email_recipient']);

        if (empty($recipients)) {
            return [
                'sent' => false,
                'message' => Text::_('COM_NXPEASYFORMS_EMAIL_NO_RECIPIENT'),
                'error' => null,
            ];
        }

        $subject = $this->resolveSubject($config, $form);
        $fieldMeta = $context['field_meta'] ?? [];

        $message = [
            'recipients' => $recipients,
            'subject' => $subject,
            'html' => $this->buildHtmlBody($form, $fieldMeta, $submission, $context),
            'text' => $this->buildPlainTextBody($form, $fieldMeta, $submission, $context, $subject),
            'from_name' => $config['email_from_name'],
            'from_email' => $config['email_from_address'],
        ];

        $replyTo = $this->resolveReplyTo($config, $fieldMeta, $context);

        $result = $this->deliver($config, $message, $replyTo);

        return [
            'sent' => $result['sent'],
            'message' => $result['sent']
                ? Text::_('COM_NXPEASYFORMS_EMAIL_SENT')
                : ($result['error'] ?? Text::_('COM_NXPEASYFORMS_EMAIL_FAILED')),
            'error' => $result['error'] ?? null,
        ];
    }

    /**
     * Merges the given options with default values.
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     * @since 1.0.0
     */
    private function mergeWithDefaults(array $options): array
    {
        $app = Factory::getApplication();

        $componentParams = $this->componentParams instanceof Registry
            ? $this->componentParams
            : new Registry((array) $this->componentParams);

        $useGlobalDelivery = $this->shouldUseGlobalConfig($options, 'use_global_email_delivery', true);
        $useGlobalRecipient = $this->shouldUseGlobalConfig($options, 'use_global_recipient', true);
        $useGlobalFromName = $this->shouldUseGlobalConfig($options, 'use_global_from_name', true);
        $useGlobalFromEmail = $this->shouldUseGlobalConfig($options, 'use_global_from_email', true);


        $defaults = [
            'send_email' => (bool) $componentParams->get('email_send_enabled', 1),
            'email_recipient' => (string) $componentParams->get(
                'email_default_recipient',
                (string) $app->get('mailfrom')
            ),
            'email_subject' => (string) $componentParams->get(
                'email_subject',
                Text::_('COM_NXPEASYFORMS_EMAIL_DEFAULT_SUBJECT')
            ),
            'email_from_name' => (string) $componentParams->get(
                'email_from_name',
                (string) $app->get('sitename')
            ),
            'email_from_address' => (string) $componentParams->get(
                'email_from_address',
                (string) $app->get('mailfrom')
            ),
            'email_reply_to' => (string) $componentParams->get('email_reply_to', ''),
        ];

        $merged = $defaults;

        foreach ($defaults as $key => $defaultValue) {
            if (!array_key_exists($key, $options)) {
                continue;
            }

            if (
                ($key === 'email_recipient' && $useGlobalRecipient) ||
                ($key === 'email_from_name' && $useGlobalFromName) ||
                ($key === 'email_from_address' && $useGlobalFromEmail)
            ) {
                continue;
            }

            $value = $options[$key];

            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value === '' || $value === null) {
                continue;
            }

            $merged[$key] = $key === 'send_email' ? (bool) $value : $value;
        }

        $deliveryDefaults = [
            'provider' => (string) $componentParams->get('email_provider', 'joomla'),
            'sendgrid' => [
                'api_key' => (string) $componentParams->get('email_sendgrid_key', ''),
            ],
            'mailgun' => [
                'api_key' => (string) $componentParams->get('email_mailgun_key', ''),
                'domain' => (string) $componentParams->get('email_mailgun_domain', ''),
                'region' => (string) $componentParams->get('email_mailgun_region', 'us'),
            ],
            'postmark' => [
                'api_token' => (string) $componentParams->get('email_postmark_api_token', ''),
            ],
            'brevo' => [
                'api_key' => (string) $componentParams->get('email_brevo_api_key', ''),
            ],
            'amazon_ses' => [
                'access_key' => (string) $componentParams->get('email_amazon_ses_access_key', ''),
                'secret_key' => (string) $componentParams->get('email_amazon_ses_secret_key', ''),
                'region' => (string) $componentParams->get('email_amazon_ses_region', 'us-east-1'),
            ],
            'mailpit' => [
                'host' => (string) $componentParams->get('email_mailpit_host', '127.0.0.1'),
                'port' => (int) $componentParams->get('email_mailpit_port', 1025),
            ],
            'smtp2go' => [
                'api_key' => (string) $componentParams->get('email_smtp2go_key', ''),
            ],
            'smtp' => [
                'host' => (string) $componentParams->get('email_smtp_host', ''),
                'port' => (int) $componentParams->get('email_smtp_port', 587),
                'encryption' => (string) $componentParams->get('email_smtp_encryption', 'tls'),
                'username' => (string) $componentParams->get('email_smtp_username', ''),
                'password' => (string) $componentParams->get('email_smtp_password', ''),
            ],
        ];

        $deliveryOptions = isset($options['email_delivery']) && is_array($options['email_delivery'])
            ? $options['email_delivery']
            : [];

        if ($useGlobalDelivery) {
            $deliveryOptions = $deliveryDefaults;
        } else {
            $deliveryOptions['provider'] = $deliveryOptions['provider'] ?? $deliveryDefaults['provider'];
        }

        $providerKeys = [
            'sendgrid' => ['api_key'],
            'mailgun' => ['api_key', 'domain', 'region'],
            'postmark' => ['api_token'],
            'brevo' => ['api_key'],
            'amazon_ses' => ['access_key', 'secret_key', 'region'],
            'mailpit' => ['host', 'port'],
            'smtp2go' => ['api_key'],
            'smtp' => ['host', 'port', 'encryption', 'username', 'password'],
        ];

        foreach ($providerKeys as $provider => $keys) {
            $deliveryOptions[$provider] = isset($deliveryOptions[$provider]) && is_array($deliveryOptions[$provider])
                ? $deliveryOptions[$provider]
                : [];

            foreach ($keys as $key) {
                if (
                    !array_key_exists($key, $deliveryOptions[$provider]) ||
                    $deliveryOptions[$provider][$key] === '' ||
                    $deliveryOptions[$provider][$key] === null
                ) {
                    $deliveryOptions[$provider][$key] = $deliveryDefaults[$provider][$key] ?? ($key === 'port' ? 0 : '');
                }
            }

            if ($provider === 'mailgun') {
                $deliveryOptions[$provider]['region'] = strtolower(
                    (string) ($deliveryOptions[$provider]['region'] ?? 'us')
                );
            }

            if ($provider === 'amazon_ses') {
                $deliveryOptions[$provider]['region'] = strtolower(
                    (string) ($deliveryOptions[$provider]['region'] ?? 'us-east-1')
                );
            }

            if ($provider === 'mailpit') {
                $deliveryOptions[$provider]['port'] = (int) ($deliveryOptions[$provider]['port'] ?? 1025) ?: 1025;
            }

            if ($provider === 'smtp') {
                $deliveryOptions[$provider]['port'] = (int) ($deliveryOptions[$provider]['port'] ?? 587) ?: 587;

                if (
                    ($deliveryOptions[$provider]['password'] ?? '') === '' &&
                    !empty($deliveryOptions[$provider]['password_set'])
                ) {
                    $deliveryOptions[$provider]['password'] = $deliveryDefaults[$provider]['password'] ?? '';
                }
            }
        }

        $secretKeys = [
            'sendgrid' => ['api_key'],
            'mailgun' => ['api_key'],
            'postmark' => ['api_token'],
            'brevo' => ['api_key'],
            'amazon_ses' => ['access_key', 'secret_key'],
            'smtp2go' => ['api_key'],
            'smtp' => ['password'],
        ];

        foreach ($secretKeys as $provider => $keys) {
            foreach ($keys as $key) {
                $value = $deliveryOptions[$provider][$key] ?? '';

                if ($value === '') {
                    $value = $deliveryDefaults[$provider][$key] ?? '';
                }

                $deliveryOptions[$provider][$key] = $this->resolveSecretValue($value);
            }
        }

        if ($useGlobalDelivery && !empty($deliveryDefaults['provider'])) {
            $deliveryOptions['provider'] = $deliveryDefaults['provider'];
        }

        $merged['email_delivery'] = $deliveryOptions;

        if ($merged['email_recipient'] === '') {
            $siteMail = (string) $app->get('mailfrom');

            if ($siteMail !== '') {
                $merged['email_recipient'] = $siteMail;
            }
        }

        if ($merged['email_from_address'] === '') {
            $siteMail = (string) $app->get('mailfrom');

            if ($siteMail !== '') {
                $merged['email_from_address'] = $siteMail;
            }
        }

        if ($merged['email_from_name'] === '') {
            $merged['email_from_name'] = (string) $app->get('sitename');
        }

        if ($merged['email_subject'] === '') {
            $merged['email_subject'] = Text::_('COM_NXPEASYFORMS_EMAIL_DEFAULT_SUBJECT');
        }

        return $merged;
    }

    /**
     * Resolves email recipient addresses from the given array of addresses.
     *
     * @return array<int, string>
     * @since 1.0.0
     */
    private function resolveRecipients(string $recipientList): array
    {
        $parts = array_map('trim', explode(',', $recipientList));

        return array_values(
            array_filter(
                $parts,
                static fn (string $address): bool => filter_var($address, FILTER_VALIDATE_EMAIL) !== false
            )
        );
    }

    /**
     * Resolves the email subject based on the form configuration and title.
     *
     * @param array<string, mixed> $config
     * @param array<string, mixed> $form
     * @since 1.0.0
     */
    private function resolveSubject(array $config, array $form): string
    {
        $subject = $config['email_subject'] ?? Text::_('COM_NXPEASYFORMS_EMAIL_DEFAULT_SUBJECT');
        $title = isset($form['title']) ? (string) $form['title'] : '';

        if ($title !== '') {
            return Text::sprintf('COM_NXPEASYFORMS_EMAIL_SUBJECT_WITH_TITLE', $subject, $title);
        }

        return $subject;
    }

    /**
     * Let's build the email body.
     * 
     * @param array<int, array<string, mixed>> $fieldMeta
     * @param array<string, mixed> $submission
     * @param array<string, mixed> $context
     * @since 1.0.0
     */
    private function buildHtmlBody(array $form, array $fieldMeta, array $submission, array $context): string
    {
        $rows = [];

        foreach ($fieldMeta as $field) {
            $label = $field['label'] ?? $field['name'] ?? '';
            $value = $this->formatValue($field['value'] ?? '');
            $rows[] = sprintf(
                '<tr><th style="text-align:left;padding:6px 12px;background:#f5f5f5;">%s</th><td style="padding:6px 12px;">%s</td></tr>',
                htmlspecialchars($label, ENT_QUOTES, 'UTF-8'),
                $value
            );
        }

        if (empty($rows)) {
            foreach ($submission as $key => $value) {
                if ($key === '_token') {
                    continue;
                }

                $rows[] = sprintf(
                    '<tr><th style="text-align:left;padding:6px 12px;background:#f5f5f5;">%s</th><td style="padding:6px 12px;">%s</td></tr>',
                    htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8'),
                    $this->formatValue($value)
                );
            }
        }

        $metaRows = [];
        if (!empty($context['ip_address'])) {
            $metaRows[] = sprintf(
                '<tr><th style="text-align:left;padding:6px 12px;background:#f5f5f5;">%s</th><td style="padding:6px 12px;">%s</td></tr>',
                Text::_('COM_NXPEASYFORMS_EMAIL_LABEL_IP_ADDRESS'),
                htmlspecialchars((string) $context['ip_address'], ENT_QUOTES, 'UTF-8')
            );
        }

        if (!empty($context['user_agent'])) {
            $metaRows[] = sprintf(
                '<tr><th style="text-align:left;padding:6px 12px;background:#f5f5f5;">%s</th><td style="padding:6px 12px;">%s</td></tr>',
                Text::_('COM_NXPEASYFORMS_EMAIL_LABEL_USER_AGENT'),
                htmlspecialchars((string) $context['user_agent'], ENT_QUOTES, 'UTF-8')
            );
        }

        $table = sprintf(
            '<table style="width:100%%;border-collapse:collapse;border:1px solid #ddd;">%s</table>',
            implode('', $rows)
        );

        $metaTable = '';
        if (!empty($metaRows)) {
            $metaTable = sprintf(
                '<h3>%s</h3><table style="width:100%%;border-collapse:collapse;border:1px solid #ddd;">%s</table>',
                Text::_('COM_NXPEASYFORMS_EMAIL_SECTION_METADATA'),
                implode('', $metaRows)
            );
        }

        $title = htmlspecialchars((string) ($form['title'] ?? Text::_('COM_NXPEASYFORMS')), ENT_QUOTES, 'UTF-8');

        return sprintf('<h2>%s</h2>%s%s', $title, $table, $metaTable);
    }

    /**
     * Now let's build the plain text body.'
     * 
     * @param array<int, array<string, mixed>> $fieldMeta
     * @param array<string, mixed> $submission
     * @param array<string, mixed> $context
     * @since 1.0.0
     */
    private function buildPlainTextBody(
        array $form,
        array $fieldMeta,
        array $submission,
        array $context,
        string $headline
    ): string {
        $lines = [$headline];

        if (!empty($fieldMeta)) {
            foreach ($fieldMeta as $field) {
                $label = $field['label'] ?? $field['name'] ?? '';
                $value = $field['value'] ?? '';
                $rendered = is_array($value) ? implode(', ', array_map('strval', $value)) : (string) $value;
                $lines[] = $label . ': ' . $rendered;
            }
        } else {
            foreach ($submission as $key => $value) {
                if ($key === '_token') {
                    continue;
                }

                $rendered = is_array($value) ? implode(', ', array_map('strval', $value)) : (string) $value;
                $lines[] = $key . ': ' . $rendered;
            }
        }

        if (!empty($context['ip_address'])) {
            $lines[] = 'IP: ' . $context['ip_address'];
        }

        return implode("\n", $lines);
    }

    /**
     * We need to resolve the Reply-To address.
     * 
     * @param array<int, array<string, mixed>> $fieldMeta
     * @param array<string, mixed> $context
     * @since 1.0.0
     */
    private function resolveReplyTo(array $config, array $fieldMeta, array $context): ?string
    {
        if (!empty($config['email_reply_to']) && filter_var($config['email_reply_to'], FILTER_VALIDATE_EMAIL)) {
            return $config['email_reply_to'];
        }

        foreach ($fieldMeta as $field) {
            $value = $field['value'] ?? '';

            if (is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return $value;
            }
        }

        if (!empty($context['submission_email']) && filter_var($context['submission_email'], FILTER_VALIDATE_EMAIL)) {
            return $context['submission_email'];
        }

        return null;
    }

	/**
	 * Formats a given value for email display.
	 * Converts arrays to comma-separated strings and handles empty values.
	 *
	 * @param   mixed  $value  The value to format
	 *
	 * @return string The formatted value as HTML string
	 * @since 1.0.0
	 */
	private function formatValue($value): string
    {
        if (is_array($value)) {
            $value = implode(', ', array_map('strval', $value));
        }

        if (!is_string($value)) {
            $value = (string) $value;
        }

        $value = trim($value);

        if ($value === '') {
            return '<em>' . Text::_('COM_NXPEASYFORMS_EMAIL_EMPTY_VALUE') . '</em>';
        }

        return nl2br(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
    }

	/**
	 * Deliver the email message using the configured provider.
	 *
	 * This method handles the actual email delivery using one of the supported providers:
	 * - Joomla's built-in mailer (default)
	 * - SendGrid API integration
	 * - SMTP2GO API integration
	 *
	 * @param   array<string, mixed>  $config   The email configuration options
	 * @param   array<string, mixed>  $message  The email message data including recipients, subject, body etc
	 * @param   string|null           $replyTo  Optional reply-to email address
	 *
	 * @return array{sent: bool, error?: string} Delivery result with sent status and optional error message
	 * @since 1.0.0
	 */
	private function deliver(array $config, array $message, ?string $replyTo): array
    {
        $delivery = isset($config['email_delivery']) && is_array($config['email_delivery'])
            ? $config['email_delivery']
            : [];

        $provider = strtolower((string) ($delivery['provider'] ?? 'joomla'));

        return match ($provider) {
            'sendgrid' => $this->sendViaSendgrid($delivery['sendgrid'] ?? [], $message, $replyTo),
            'mailgun' => $this->sendViaMailgun($delivery['mailgun'] ?? [], $message, $replyTo),
            'postmark' => $this->sendViaPostmark($delivery['postmark'] ?? [], $message, $replyTo),
            'brevo' => $this->sendViaBrevo($delivery['brevo'] ?? [], $message, $replyTo),
            'amazon_ses' => $this->sendViaAmazonSes($delivery['amazon_ses'] ?? [], $message, $replyTo),
            'mailpit' => $this->sendViaMailpit($delivery['mailpit'] ?? [], $message, $replyTo),
            'smtp2go' => $this->sendViaSmtp2Go($delivery['smtp2go'] ?? [], $message, $replyTo),
            'smtp' => $this->sendViaCustomSmtp($delivery['smtp'] ?? [], $message, $replyTo),
            default => $this->sendViaMailer($message, $replyTo),
        };
    }

    /**
     * Sends an email via the Joomla mailer.
     *
     * @param array<string, mixed> $message
     * @return array{sent: bool, error?: string}
     * @since 1.0.0
     */
    private function sendViaMailer(array $message, ?string $replyTo): array
    {
        $mailer = clone $this->mailer;

        return $this->deliverWithMailer($mailer, $message, $replyTo, 'COM_NXPEASYFORMS_EMAIL_FAILED', true);
    }

    /**
     * Sends an email via SendGrid's API.'
     *
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $message
     * @return array{sent: bool, error?: string}
     * @since 1.0.0
     */
    private function sendViaSendgrid(array $settings, array $message, ?string $replyTo): array
    {
        $apiKey = $this->resolveSecretValue($settings['api_key'] ?? '');

        if ($apiKey === '') {
            return [
                'sent' => false,
                'error' => Text::_('COM_NXPEASYFORMS_EMAIL_SENDGRID_MISSING_KEY'),
            ];
        }

        $payload = [
            'personalizations' => [
                [
                    'to' => array_map(
                        static fn (string $email): array => ['email' => $email],
                        $message['recipients']
                    ),
                ],
            ],
            'from' => [
                'email' => $message['from_email'],
                'name' => $message['from_name'],
            ],
            'subject' => $message['subject'],
            'content' => [
                ['type' => 'text/html', 'value' => $message['html']],
                ['type' => 'text/plain', 'value' => $message['text']],
            ],
        ];

        if ($replyTo !== null) {
            $payload['reply_to'] = ['email' => $replyTo];
        }

        try {
            $response = $this->httpClient->sendJson(
                'https://api.sendgrid.com/v3/mail/send',
                $payload,
                'POST',
                ['Authorization' => 'Bearer ' . $apiKey],
                15
            );
        } catch (\Throwable $exception) {
            return [
                'sent' => false,
                'error' => $exception->getMessage(),
            ];
        }

        $code = (int) $response->code;

        if ($code >= 200 && $code < 300) {
            return ['sent' => true];
        }

        return [
            'sent' => false,
            'error' => Text::sprintf('COM_NXPEASYFORMS_EMAIL_SENDGRID_ERROR', $code),
        ];
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $message
     * @return array{sent: bool, error?: string}
     * @since 1.0.0
     */
    private function sendViaSmtp2Go(array $settings, array $message, ?string $replyTo): array
    {
        $apiKey = $this->resolveSecretValue($settings['api_key'] ?? '');

        if ($apiKey === '') {
            return [
                'sent' => false,
                'error' => Text::_('COM_NXPEASYFORMS_EMAIL_SMTP2GO_MISSING_KEY'),
            ];
        }

        $payload = [
            'api_key' => $apiKey,
            'to' => array_values($message['recipients']),
            'sender' => $message['from_email'],
            'subject' => $message['subject'],
            'html_body' => $message['html'],
            'text_body' => $message['text'],
        ];

        if ($replyTo !== null) {
            $payload['reply_to'] = $replyTo;
        }

        try {
            $response = $this->httpClient->sendJson(
                'https://api.smtp2go.com/v3/email/send',
                $payload,
                'POST',
                [],
                15
            );
        } catch (\Throwable $exception) {
            return [
                'sent' => false,
                'error' => $exception->getMessage(),
            ];
        }

        $code = (int) $response->code;

        if ($code >= 200 && $code < 300) {
            return ['sent' => true];
        }

        return [
            'sent' => false,
            'error' => Text::sprintf('COM_NXPEASYFORMS_EMAIL_SMTP2GO_ERROR', $code),
        ];
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $message
     * @return array{sent: bool, error?: string}
     */
    private function sendViaMailgun(array $settings, array $message, ?string $replyTo): array
    {
        $apiKey = $this->resolveSecretValue($settings['api_key'] ?? '');
        $domain = trim((string) ($settings['domain'] ?? ''));

        if ($apiKey === '' || $domain === '') {
            return [
                'sent' => false,
                'error' => Text::_('COM_NXPEASYFORMS_EMAIL_MAILGUN_MISSING_CONFIG'),
            ];
        }

        $region = strtolower((string) ($settings['region'] ?? 'us'));
        $endpoint = $region === 'eu' ? 'https://api.eu.mailgun.net' : 'https://api.mailgun.net';
        $url = $endpoint . '/v3/' . $domain . '/messages';

        $fields = [
            'from' => $this->formatEmailAddress($message['from_email'], $message['from_name']),
            'to' => implode(',', $message['recipients']),
            'subject' => $message['subject'],
            'html' => $message['html'],
            'text' => $message['text'],
        ];

        if ($replyTo !== null) {
            $fields['h:Reply-To'] = $replyTo;
        }

        try {
            $response = $this->httpClient->sendForm(
                $url,
                $fields,
                'POST',
                ['Authorization' => 'Basic ' . base64_encode('api:' . $apiKey)],
                15
            );
        } catch (\Throwable $exception) {
            return [
                'sent' => false,
                'error' => $exception->getMessage(),
            ];
        }

        $code = (int) $response->code;

        if ($code >= 200 && $code < 300) {
            return ['sent' => true];
        }

        return [
            'sent' => false,
            'error' => Text::sprintf('COM_NXPEASYFORMS_EMAIL_MAILGUN_ERROR', $code),
        ];
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $message
     * @return array{sent: bool, error?: string}
     */
    private function sendViaPostmark(array $settings, array $message, ?string $replyTo): array
    {
        $token = $this->resolveSecretValue($settings['api_token'] ?? '');

        if ($token === '') {
            return [
                'sent' => false,
                'error' => Text::_('COM_NXPEASYFORMS_EMAIL_POSTMARK_MISSING_TOKEN'),
            ];
        }

        $payload = [
            'From' => $this->formatEmailAddress($message['from_email'], $message['from_name']),
            'To' => implode(',', $message['recipients']),
            'Subject' => $message['subject'],
            'HtmlBody' => $message['html'],
            'TextBody' => $message['text'],
        ];

        if ($replyTo !== null) {
            $payload['ReplyTo'] = $replyTo;
        }

        try {
            $response = $this->httpClient->sendJson(
                'https://api.postmarkapp.com/email',
                $payload,
                'POST',
                ['X-Postmark-Server-Token' => $token],
                15
            );
        } catch (\Throwable $exception) {
            return [
                'sent' => false,
                'error' => $exception->getMessage(),
            ];
        }

        $code = (int) $response->code;

        if ($code >= 200 && $code < 300) {
            return ['sent' => true];
        }

        return [
            'sent' => false,
            'error' => Text::sprintf('COM_NXPEASYFORMS_EMAIL_POSTMARK_ERROR', $code),
        ];
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $message
     * @return array{sent: bool, error?: string}
     */
    private function sendViaBrevo(array $settings, array $message, ?string $replyTo): array
    {
        $apiKey = $this->resolveSecretValue($settings['api_key'] ?? '');

        if ($apiKey === '') {
            return [
                'sent' => false,
                'error' => Text::_('COM_NXPEASYFORMS_EMAIL_BREVO_MISSING_KEY'),
            ];
        }

        $payload = [
            'sender' => [
                'email' => $message['from_email'],
                'name' => $message['from_name'],
            ],
            'to' => array_map(
                static fn (string $email): array => ['email' => $email],
                $message['recipients']
            ),
            'subject' => $message['subject'],
            'htmlContent' => $message['html'],
            'textContent' => $message['text'],
        ];

        if ($replyTo !== null) {
            $payload['replyTo'] = ['email' => $replyTo];
        }

        try {
            $response = $this->httpClient->sendJson(
                'https://api.brevo.com/v3/smtp/email',
                $payload,
                'POST',
                ['api-key' => $apiKey],
                15
            );
        } catch (\Throwable $exception) {
            return [
                'sent' => false,
                'error' => $exception->getMessage(),
            ];
        }

        $code = (int) $response->code;

        if ($code >= 200 && $code < 300) {
            return ['sent' => true];
        }

        return [
            'sent' => false,
            'error' => Text::sprintf('COM_NXPEASYFORMS_EMAIL_BREVO_ERROR', $code),
        ];
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $message
     * @return array{sent: bool, error?: string}
     */
    private function sendViaAmazonSes(array $settings, array $message, ?string $replyTo): array
    {
        $accessKey = $this->resolveSecretValue($settings['access_key'] ?? '');
        $secretKey = $this->resolveSecretValue($settings['secret_key'] ?? '');
        $region = strtolower((string) ($settings['region'] ?? 'us-east-1'));

        if ($accessKey === '' || $secretKey === '') {
            return [
                'sent' => false,
                'error' => Text::_('COM_NXPEASYFORMS_EMAIL_AMAZON_SES_MISSING_KEYS'),
            ];
        }

        $host = sprintf('email-smtp.%s.amazonaws.com', $region !== '' ? $region : 'us-east-1');

        return $this->sendViaSmtpRelay(
            [
                'host' => $host,
                'port' => 587,
                'encryption' => 'tls',
                'username' => $accessKey,
                'password' => $secretKey,
            ],
            $message,
            $replyTo,
            'COM_NXPEASYFORMS_EMAIL_AMAZON_SES_MISSING_KEYS',
            'COM_NXPEASYFORMS_EMAIL_AMAZON_SES_ERROR'
        );
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $message
     * @return array{sent: bool, error?: string}
     */
    private function sendViaMailpit(array $settings, array $message, ?string $replyTo): array
    {
        $host = trim((string) ($settings['host'] ?? ''));
        $port = (int) ($settings['port'] ?? 1025);

        if ($host === '') {
            return [
                'sent' => false,
                'error' => Text::_('COM_NXPEASYFORMS_EMAIL_MAILPIT_MISSING_HOST'),
            ];
        }

        return $this->sendViaSmtpRelay(
            [
                'host' => $host,
                'port' => $port > 0 ? $port : 1025,
                'encryption' => '',
                'username' => '',
                'password' => '',
            ],
            $message,
            $replyTo,
            'COM_NXPEASYFORMS_EMAIL_MAILPIT_MISSING_HOST',
            'COM_NXPEASYFORMS_EMAIL_MAILPIT_ERROR',
            true
        );
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $message
     * @return array{sent: bool, error?: string}
     */
    private function sendViaCustomSmtp(array $settings, array $message, ?string $replyTo): array
    {
        return $this->sendViaSmtpRelay(
            $settings,
            $message,
            $replyTo,
            'COM_NXPEASYFORMS_EMAIL_SMTP_MISSING_CONFIG',
            'COM_NXPEASYFORMS_EMAIL_SMTP_ERROR'
        );
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $message
     * @return array{sent: bool, error?: string}
     */
    private function sendViaSmtpRelay(
        array $settings,
        array $message,
        ?string $replyTo,
        string $missingConfigLangKey,
        string $errorLangKey,
        bool $allowAnonymous = false
    ): array {
        $host = trim((string) ($settings['host'] ?? ''));

        if ($host === '') {
            return [
                'sent' => false,
                'error' => Text::_($missingConfigLangKey),
            ];
        }

        $port = (int) ($settings['port'] ?? 0);
        $port = $port > 0 ? $port : 25;

        $encryption = strtolower((string) ($settings['encryption'] ?? ''));
        if (!in_array($encryption, ['ssl', 'tls'], true)) {
            $encryption = '';
        }

        $username = trim((string) ($settings['username'] ?? ''));
        $password = $this->resolveSecretValue($settings['password'] ?? '');

        if (!$allowAnonymous && ($username === '' || $password === '')) {
            return [
                'sent' => false,
                'error' => Text::_($missingConfigLangKey),
            ];
        }

        $config = new Registry(
            [
                'mailer' => 'smtp',
                'smtphost' => $host,
                'smtpport' => $port,
                'smtpsecure' => $encryption,
                'smtpuser' => $username,
                'smtppass' => $password,
                'smtpauth' => ($username !== '' && $password !== '') ? 1 : 0,
                'mailfrom' => $message['from_email'],
                'fromname' => $message['from_name'],
                'throw_exceptions' => true,
            ]
        );

        if ($allowAnonymous && $username === '' && $password === '') {
            $config->set('smtpauth', 0);
        }

        try {
            $mailer = $this->mailerFactory->createMailer($config);
        } catch (\Throwable $exception) {
            return [
                'sent' => false,
                'error' => $exception->getMessage(),
            ];
        }

        return $this->deliverWithMailer($mailer, $message, $replyTo, $errorLangKey);
    }

    /**
     * @param array<string, mixed> $message
     * @return array{sent: bool, error?: string}
     */
    private function deliverWithMailer(
        MailerInterface $mailer,
        array $message,
        ?string $replyTo,
        string $errorLangKey,
        bool $resetRecipients = false
    ): array {
        if ($resetRecipients) {
            if (method_exists($mailer, 'clearAllRecipients')) {
                $mailer->clearAllRecipients();
            }
            if (method_exists($mailer, 'clearReplyTos')) {
                $mailer->clearReplyTos();
            }
            if (method_exists($mailer, 'clearCCs')) {
                $mailer->clearCCs();
            }
            if (method_exists($mailer, 'clearBCCs')) {
                $mailer->clearBCCs();
            }
            if (method_exists($mailer, 'clearAttachments')) {
                $mailer->clearAttachments();
            }
        }

        try {
            $mailer->setSender($message['from_email'], $message['from_name']);
        } catch (\Throwable $exception) {
            return [
                'sent' => false,
                'error' => $exception->getMessage(),
            ];
        }

        try {
            foreach ($message['recipients'] as $recipient) {
                $mailer->addRecipient($recipient);
            }
        } catch (\Throwable $exception) {
            return [
                'sent' => false,
                'error' => $exception->getMessage(),
            ];
        }

        if ($replyTo !== null) {
            try {
                $mailer->addReplyTo($replyTo);
            } catch (\Throwable $exception) {
                return [
                    'sent' => false,
                    'error' => $exception->getMessage(),
                ];
            }
        }

        $mailer->setSubject($message['subject']);

        if (method_exists($mailer, 'isHtml')) {
            $mailer->isHtml(true);
        }

        try {
            $mailer->setBody($message['html']);
        } catch (\Throwable $exception) {
            return [
                'sent' => false,
                'error' => $exception->getMessage(),
            ];
        }

        if (property_exists($mailer, 'AltBody')) {
            $mailer->AltBody = $message['text'];
        }

        try {
            $result = $mailer->send();
        } catch (\Throwable $exception) {
            return [
                'sent' => false,
                'error' => $exception->getMessage(),
            ];
        }

        $sent = $result === null ? true : (bool) $result;

        if ($sent) {
            return ['sent' => true];
        }

        return [
            'sent' => false,
            'error' => Text::_($errorLangKey),
        ];
    }

    private function formatEmailAddress(string $email, string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            return $email;
        }

        return sprintf('%s <%s>', $name, $email);
    }

    /**
     * Determine whether a form option should fall back to the global component configuration.
     *
     * @param array<string,mixed> $options Options array from the form.
     * @param string $key The option key to inspect.
     * @param bool $default Default value when the key is missing or empty.
     *
     * @return bool
     * @since 1.0.0
     */
    private function shouldUseGlobalConfig(array $options, string $key, bool $default = true): bool
    {
        if (!array_key_exists($key, $options)) {
            return $default;
        }

        $value = $options[$key];

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if ($normalized === '') {
                return $default;
            }

            if (in_array($normalized, ['0', 'false', 'off', 'no'], true)) {
                return false;
            }

            if (in_array($normalized, ['1', 'true', 'on', 'yes'], true)) {
                return true;
            }
        }

        return (bool) $value;
    }

		/**
		 * Resolves a secret value by decrypting it if necessary.
		 *
		 * @param string|null $value The secret value to resolve
		 * @return string The decrypted or original value
		 * @since 1.0.0
		 */
    private function resolveSecretValue(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $decrypted = Secrets::decrypt($value);

        return $decrypted !== '' ? $decrypted : $value;
    }
}
