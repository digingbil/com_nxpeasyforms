<?php

declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Tests\Email;

use Joomla\CMS\Mail\MailerStub;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Email\EmailService;
use Joomla\Registry\Registry;
use PHPUnit\Framework\TestCase;

final class EmailServiceTest extends TestCase
{
    public function testDispatchSubmissionSendsUsingMailer(): void
    {
        $registry = new Registry();
        $registry->set('email_send_enabled', 1);
        $registry->set('email_default_recipient', 'recipient@example.com');
        $registry->set('email_from_name', 'Test Site');
        $registry->set('email_from_address', 'noreply@example.com');

        $service = new EmailService(new MailerStub(), $registry);

        $form = [
            'title' => 'Contact',
            'config' => [
                'options' => [
                    'send_email' => true,
                ],
            ],
        ];

        $result = $service->dispatchSubmission($form, ['name' => 'Alice'], []);

        $this->assertTrue($result['sent']);
    }
}
