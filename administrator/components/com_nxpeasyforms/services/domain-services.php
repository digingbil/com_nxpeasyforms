<?php
declare(strict_types=1);

use Joomla\Component\Nxpeasyforms\Administrator\Service\Email\EmailService;
use Joomla\Component\Nxpeasyforms\Administrator\Service\File\FileUploader;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations\GenericWebhookDispatcher;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations\HttpClient;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations\HubspotDispatcher;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations\IntegrationManager;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations\IntegrationQueue;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations\JoomlaArticleDispatcher;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations\MailchimpDispatcher;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations\SalesforceDispatcher;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations\SlackDispatcher;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations\TeamsDispatcher;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations\WebhookDispatcher;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Authentication\UserLoginHandler;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Registration\UserRegistrationHandler;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Repository\FormRepository;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Repository\SubmissionRepository;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Rendering\MessageFormatter;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Rendering\TemplateRenderer;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Security\CaptchaService;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Security\IpHandler;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Security\RateLimiter;
use Joomla\Component\Nxpeasyforms\Administrator\Service\SubmissionService;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Validation\FieldValidator;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Validation\FileValidator;
use Joomla\Database\DatabaseDriver;
use Joomla\DI\Container;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

return static function (Container $container): void {
    $container->share(
        FormRepository::class,
        static function (Container $container): FormRepository {
            return new FormRepository($container->get(DatabaseDriver::class));
        }
    );

    $container->share(
        SubmissionRepository::class,
        static function (Container $container): SubmissionRepository {
            return new SubmissionRepository($container->get(DatabaseDriver::class));
        }
    );

    $container->share(
        FileValidator::class,
        static function (): FileValidator {
            return new FileValidator();
        }
    );

    $container->share(
        FieldValidator::class,
        static function (Container $container): FieldValidator {
            return new FieldValidator($container->get(FileValidator::class));
        }
    );

    $container->share(
        CaptchaService::class,
        static function (): CaptchaService {
            return new CaptchaService();
        }
    );

    $container->share(
        RateLimiter::class,
        static function (): RateLimiter {
            return new RateLimiter();
        }
    );

    $container->share(
        IpHandler::class,
        static function (): IpHandler {
            return new IpHandler();
        }
    );

    $container->share(
        FileUploader::class,
        static function (Container $container): FileUploader {
            return new FileUploader($container->get(FileValidator::class));
        }
    );

    $container->share(
        EmailService::class,
        static function (): EmailService {
            return new EmailService();
        }
    );

    $container->share(
        HttpClient::class,
        static function (): HttpClient {
            return new HttpClient();
        }
    );

    $container->share(
        TemplateRenderer::class,
        static function (): TemplateRenderer {
            return new TemplateRenderer();
        }
    );

    $container->share(
        MessageFormatter::class,
        static function (Container $container): MessageFormatter {
            return new MessageFormatter($container->get(TemplateRenderer::class));
        }
    );

    $container->share(
        WebhookDispatcher::class,
        static function (Container $container): WebhookDispatcher {
            return new WebhookDispatcher(
                null,
                $container->get(HttpClient::class),
                null
            );
        }
    );

    $container->share(
        GenericWebhookDispatcher::class,
        static function (Container $container): GenericWebhookDispatcher {
            return new GenericWebhookDispatcher(
                $container->get(HttpClient::class)
            );
        }
    );

    $container->share(
        MailchimpDispatcher::class,
        static function (Container $container): MailchimpDispatcher {
            return new MailchimpDispatcher(
                $container->get(HttpClient::class),
                $container->get(TemplateRenderer::class),
                null
            );
        }
    );

    $container->share(
        HubspotDispatcher::class,
        static function (Container $container): HubspotDispatcher {
            return new HubspotDispatcher(
                $container->get(HttpClient::class),
                $container->get(TemplateRenderer::class),
                null
            );
        }
    );

    $container->share(
        JoomlaArticleDispatcher::class,
        static function (): JoomlaArticleDispatcher {
            return new JoomlaArticleDispatcher();
        }
    );

    $container->share(
        SlackDispatcher::class,
        static function (Container $container): SlackDispatcher {
            return new SlackDispatcher(
                $container->get(HttpClient::class),
                $container->get(MessageFormatter::class),
                $container->get(TemplateRenderer::class)
            );
        }
    );

    $container->share(
        TeamsDispatcher::class,
        static function (Container $container): TeamsDispatcher {
            return new TeamsDispatcher(
                $container->get(HttpClient::class),
                $container->get(MessageFormatter::class),
                $container->get(TemplateRenderer::class)
            );
        }
    );

    $container->share(
        SalesforceDispatcher::class,
        static function (Container $container): SalesforceDispatcher {
            return new SalesforceDispatcher(
                $container->get(HttpClient::class),
                $container->get(TemplateRenderer::class),
                null
            );
        }
    );

    $container->share(
        IntegrationQueue::class,
        static function (): IntegrationQueue {
            return new IntegrationQueue();
        }
    );

    $container->share(
        UserRegistrationHandler::class,
        static function (Container $container): UserRegistrationHandler {
            return new UserRegistrationHandler($container->get(DatabaseDriver::class));
        }
    );

    $container->share(
        UserLoginHandler::class,
        static function (Container $container): UserLoginHandler {
            return new UserLoginHandler(null, $container->get(DatabaseDriver::class));
        }
    );

    $container->share(
        IntegrationManager::class,
        static function (Container $container): IntegrationManager {
            $manager = new IntegrationManager();
            $manager->register('webhook', $container->get(WebhookDispatcher::class));
            $manager->register('joomla_article', $container->get(JoomlaArticleDispatcher::class));
            $manager->register('zapier', $container->get(GenericWebhookDispatcher::class));
            $manager->register('make', $container->get(GenericWebhookDispatcher::class));
            $manager->register('slack', $container->get(SlackDispatcher::class));
            $manager->register('teams', $container->get(TeamsDispatcher::class));
            $manager->register('mailchimp', $container->get(MailchimpDispatcher::class));
            $manager->register('hubspot', $container->get(HubspotDispatcher::class));
            $manager->register('salesforce', $container->get(SalesforceDispatcher::class));

            return $manager;
        }
    );

    $container->share(
        SubmissionService::class,
        static function (Container $container): SubmissionService {
            return new SubmissionService(
                $container->get(FormRepository::class),
                $container->get(SubmissionRepository::class),
                $container->get(FieldValidator::class),
                $container->get(CaptchaService::class),
                $container->get(RateLimiter::class),
                $container->get(IpHandler::class),
                null,
                $container->get(FileUploader::class),
                $container->get(EmailService::class),
                $container->get(IntegrationManager::class),
                $container->get(IntegrationQueue::class),
                $container->get(UserRegistrationHandler::class),
                $container->get(UserLoginHandler::class)
            );
        }
    );
};
