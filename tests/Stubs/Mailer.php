<?php

declare(strict_types=1);

namespace Joomla\CMS\Mail;

interface MailerInterface
{
    public function clearAllRecipients();
    public function clearReplyTos();
    public function clearCCs();
    public function clearBCCs();
    public function clearAttachments();
    public function setSender($from);
    public function addRecipient($recipient);
    public function setSubject(string $subject);
    public function isHtml(bool $value = true);
    public function setBody(string $body);
    public function addReplyTo($address, $name = ''): void;
    public function send();
}

final class MailerStub implements MailerInterface
{
    public array $recipients = [];

    public function clearAllRecipients(): void {}

    public function clearReplyTos(): void {}

    public function clearCCs(): void {}

    public function clearBCCs(): void {}

    public function clearAttachments(): void {}

    public function setSender($from): void {}

    public function addRecipient($recipient): void
    {
        $this->recipients[] = $recipient;
    }

    public function setSubject(string $subject): void {}

    public function isHtml(bool $value = true): void {}

    public function setBody(string $body): void {}

    public function addReplyTo($address, $name = ''): void {}

    public function send(): bool
    {
        return true;
    }
}
