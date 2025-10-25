<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Service\Email;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Mail\MailerInterface;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations\HttpClient;
use Joomla\Component\Nxpeasyforms\Administrator\Support\Secrets;
use Joomla\Registry\Registry;


use function array_filter;
use function array_key_exists;
use function array_map;
use function array_values;
use function explode;
use function filter_var;
use function htmlspecialchars;
use function implode;
use function is_array;
use function is_string;
use function nl2br;
use function sprintf;
use function trim;

use const FILTER_VALIDATE_EMAIL;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

final class EmailService
{
    private MailerInterface $mailer;

    private Registry $componentParams;

    private HttpClient $httpClient;

    public function __construct(
        ?MailerInterface $mailer = null,
        ?Registry $componentParams = null,
        ?HttpClient $httpClient = null
    ) {
        $container = Factory::getContainer();

        /** @var MailerInterface $mailerInstance */
        $mailerInstance = $mailer ?? $container->get(MailerInterface::class);
        $this->mailer = $mailerInstance;

        $this->componentParams = $componentParams ?? ComponentHelper::getParams('com_nxpeasyforms');
        $this->httpClient = $httpClient ?? new HttpClient();
    }

    /**
     * @param array<string, mixed> $form
     * @param array<string, mixed> $submission
     * @param array<string, mixed> $context
     *
     * @return array{sent: bool, message: string, error?: string|null}
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
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function mergeWithDefaults(array $options): array
    {
        $app = Factory::getApplication();

        $defaults = [
            'send_email' => (bool) $this->componentParams->get('email_send_enabled', 1),
            'email_recipient' => (string) $this->componentParams->get(
                'email_default_recipient',
                (string) $app->get('mailfrom')
            ),
            'email_subject' => (string) $this->componentParams->get(
                'email_subject',
                Text::_('COM_NXPEASYFORMS_EMAIL_DEFAULT_SUBJECT')
            ),
            'email_from_name' => (string) $this->componentParams->get(
                'email_from_name',
                (string) $app->get('sitename')
            ),
            'email_from_address' => (string) $this->componentParams->get(
                'email_from_address',
                (string) $app->get('mailfrom')
            ),
            'email_reply_to' => (string) $this->componentParams->get('email_reply_to', ''),
        ];

        $merged = $defaults;

        foreach ($defaults as $key => $defaultValue) {
            if (!array_key_exists($key, $options)) {
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
            'provider' => (string) $this->componentParams->get('email_provider', 'joomla'),
            'sendgrid' => [
                'api_key' => (string) $this->componentParams->get('email_sendgrid_key', ''),
            ],
            'smtp2go' => [
                'api_key' => (string) $this->componentParams->get('email_smtp2go_key', ''),
            ],
        ];

        $deliveryOptions = isset($options['email_delivery']) && is_array($options['email_delivery'])
            ? $options['email_delivery']
            : [];

        $deliveryOptions['provider'] = $deliveryOptions['provider'] ?? $deliveryDefaults['provider'];

        foreach (['sendgrid', 'smtp2go'] as $provider) {
            $deliveryOptions[$provider] = isset($deliveryOptions[$provider]) && is_array($deliveryOptions[$provider])
                ? $deliveryOptions[$provider]
                : [];

            if (empty($deliveryOptions[$provider]['api_key'])) {
                $deliveryOptions[$provider]['api_key'] = $deliveryDefaults[$provider]['api_key'];
            }
        }

        $merged['email_delivery'] = $deliveryOptions;

        return $merged;
    }

    /**
     * @return array<int, string>
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
     * @param array<string, mixed> $config
     * @param array<string, mixed> $form
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
     * @param array<int, array<string, mixed>> $fieldMeta
     * @param array<string, mixed> $submission
     * @param array<string, mixed> $context
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
     * @param array<int, array<string, mixed>> $fieldMeta
     * @param array<string, mixed> $submission
     * @param array<string, mixed> $context
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
     * @param array<int, array<string, mixed>> $fieldMeta
     * @param array<string, mixed> $context
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
     * @param mixed $value
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
     * @param array<string, mixed> $config
     * @param array<string, mixed> $message
     * @return array{sent: bool, error?: string}
     */
    private function deliver(array $config, array $message, ?string $replyTo): array
    {
        $delivery = isset($config['email_delivery']) && is_array($config['email_delivery'])
            ? $config['email_delivery']
            : [];

        $provider = $delivery['provider'] ?? 'joomla';

        return match ($provider) {
            'sendgrid' => $this->sendViaSendgrid($delivery['sendgrid'] ?? [], $message, $replyTo),
            'smtp2go' => $this->sendViaSmtp2Go($delivery['smtp2go'] ?? [], $message, $replyTo),
            default => $this->sendViaMailer($message, $replyTo),
        };
    }

    /**
     * @param array<string, mixed> $message
     * @return array{sent: bool, error?: string}
     */
    private function sendViaMailer(array $message, ?string $replyTo): array
    {
        $mailer = clone $this->mailer;
        $mailer->clearAllRecipients();
        $mailer->clearReplyTos();
        $mailer->clearCCs();
        $mailer->clearBCCs();
        $mailer->clearAttachments();

        $mailer->setSender([$message['from_email'], $message['from_name']]);

        foreach ($message['recipients'] as $recipient) {
            $mailer->addRecipient($recipient);
        }

        if ($replyTo !== null) {
            $mailer->addReplyTo($replyTo);
        }

        $mailer->setSubject($message['subject']);
        $mailer->isHtml(true);
        $mailer->setBody($message['html']);

        try {
            $sent = (bool) $mailer->send();
        } catch (\Throwable $exception) {
            return [
                'sent' => false,
                'error' => $exception->getMessage(),
            ];
        }

        return [
            'sent' => $sent,
            'error' => $sent ? null : Text::_('COM_NXPEASYFORMS_EMAIL_FAILED'),
        ];
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $message
     * @return array{sent: bool, error?: string}
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

    private function resolveSecretValue(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $decrypted = Secrets::decrypt($value);

        return $decrypted !== '' ? $decrypted : $value;
    }
}
