<?php

declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Tests\Service;

use Joomla\CMS\Mail\MailerStub;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Email\EmailService;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Exception\SubmissionException;
use Joomla\Component\Nxpeasyforms\Administrator\Service\File\FileUploader;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations\IntegrationManager;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations\IntegrationQueue;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Repository\FormRepository;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Repository\SubmissionRepository;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Security\CaptchaService;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Security\IpHandler;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Security\RateLimiter;
use Joomla\Component\Nxpeasyforms\Administrator\Service\SubmissionService;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Validation\FieldValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Joomla\Registry\Registry;

final class SubmissionServiceTest extends TestCase
{
    public function testRejectsInactiveForm(): void
    {
        $forms = $this->createMock(FormRepository::class);
        $forms->method('find')->willReturn([
            'active' => 0,
            'config' => ['fields' => [], 'options' => []],
        ]);

        $service = new SubmissionService(
            $forms,
            $this->createStub(SubmissionRepository::class),
            new FieldValidator(),
            new CaptchaService(),
            new RateLimiter(new Psr16Cache(new ArrayAdapter())),
            new IpHandler(),
            null,
            new FileUploader(),
            new EmailService(new MailerStub(), new Registry()),
            new IntegrationManager(),
            new IntegrationQueue(new Psr16Cache(new ArrayAdapter()))
        );

        $this->expectException(SubmissionException::class);

        $service->handle(1, [], ['skip_token_validation' => true]);
    }

    public function testSuccessfulSubmissionReturnsSuccessPayload(): void
    {
        $forms = $this->createMock(FormRepository::class);
        $forms->method('find')->willReturn([
            'id' => 1,
            'title' => 'Contact',
            'active' => 1,
            'config' => [
                'fields' => [
                    ['type' => 'text', 'name' => 'name'],
                ],
                'options' => ['send_email' => false, 'store_submissions' => false],
            ],
        ]);

        $service = new SubmissionService(
            $forms,
            $this->createStub(SubmissionRepository::class),
            new FieldValidator(),
            new CaptchaService(),
            new RateLimiter(new Psr16Cache(new ArrayAdapter())),
            new IpHandler(),
            null,
            new FileUploader(),
            new EmailService(new MailerStub(), new Registry()),
            new IntegrationManager(),
            new IntegrationQueue(new Psr16Cache(new ArrayAdapter()))
        );

        $result = $service->handle(1, ['_token' => '', 'name' => 'Alice'], ['skip_token_validation' => true]);

        $this->assertTrue($result['success']);
        $this->assertSame('Alice', $result['data']['name']);
    }
}
