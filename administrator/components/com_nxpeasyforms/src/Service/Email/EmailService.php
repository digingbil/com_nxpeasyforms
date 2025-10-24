<?php

declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Service\Email;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Mail\MailerInterface;
use Joomla\Registry\Registry;

use function array_filter;
use function array_key_exists;
use function array_map;
use function explode;
use function filter_var;
use function htmlspecialchars;
use function implode;
use function is_array;
use function is_string;
use function nl2br;
use function trim;

use const FILTER_VALIDATE_EMAIL;

/**
 * Handles notification email delivery for submissions.
 */
final class EmailService
{
    private MailerInterface $mailer;

    private Registry $componentParams;

    public function __construct(?MailerInterface $mailer = null, ?Registry $componentParams = null)
    {
        $container = Factory::getContainer();

        /** @var MailerInterface $mailerInstance */
        $mailerInstance = $mailer ?? $container->get(MailerInterface::class);
        $this->mailer = $mailerInstance;
        $this->componentParams = $componentParams ?? ComponentHelper::getParams('com_nxpeasyforms');
    }

    /**
     * @param array<string, mixed> $form
     * @param array<string, mixed> $submission
     * @param array<string, mixed> $context
     *
     * @return array{sent: bool, message: string}
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
            ];
        }

        $recipients = $this->resolveRecipients($config['email_recipient']);

        if (empty($recipients)) {
            return [
                'sent' => false,
                'message' => Text::_('COM_NXPEASYFORMS_EMAIL_NO_RECIPIENT'),
            ];
        }

        $mailer = clone $this->mailer;
        $mailer->clearAllRecipients();
        $mailer->clearReplyTos();
        $mailer->clearCCs();
        $mailer->clearBCCs();
        $mailer->clearAttachments();

        $mailer->setSender([$config['email_from_address'], $config['email_from_name']]);

        foreach ($recipients as $recipient) {
            $mailer->addRecipient($recipient);
        }

        $subject = $this->resolveSubject($config, $form);
        $mailer->setSubject($subject);

        $fieldMeta = $context['field_meta'] ?? [];
        $htmlBody = $this->buildHtmlBody($form, $fieldMeta, $submission, $context);
        $mailer->isHtml(true);
        $mailer->setBody($htmlBody);

        $replyTo = $this->resolveReplyTo($config, $fieldMeta, $context);
        if ($replyTo !== null) {
            $mailer->addReplyTo($replyTo);
        }

        try {
            $sent = (bool) $mailer->send();
        } catch (\Throwable $exception) {
            return [
                'sent' => false,
                'message' => Text::sprintf(
                    'COM_NXPEASYFORMS_EMAIL_ERROR_EXCEPTION',
                    $exception->getMessage()
                ),
            ];
        }

        return [
            'sent' => $sent,
            'message' => $sent
                ? Text::_('COM_NXPEASYFORMS_EMAIL_SENT')
                : Text::_('COM_NXPEASYFORMS_EMAIL_FAILED'),
        ];
    }

    /**
     * @param array<string, mixed> $options
     *
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

            if ($key === 'send_email') {
                $merged[$key] = (bool) $value;
            } else {
                $merged[$key] = $value;
            }
        }

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

        return sprintf(
            '<h2>%s</h2>%s%s',
            $title,
            $table,
            $metaTable
        );
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
}
