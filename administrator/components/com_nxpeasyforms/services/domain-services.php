<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */
declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\Component\Nxpeasyforms\Administrator\Ajax\AjaxRouter;
use Joomla\Component\Nxpeasyforms\Administrator\Ajax\Handler\EmailAjaxHandler;
use Joomla\Component\Nxpeasyforms\Administrator\Ajax\Handler\FormAjaxHandler;
use Joomla\Component\Nxpeasyforms\Administrator\Ajax\Handler\Integrations\MailchimpAjaxHandler;
use Joomla\Component\Nxpeasyforms\Administrator\Ajax\Handler\Settings\EmailSettingsAjaxHandler;
use Joomla\Component\Nxpeasyforms\Administrator\Ajax\Handler\Settings\JoomlaSettingsAjaxHandler;
use Joomla\Component\Nxpeasyforms\Administrator\Ajax\Support\CategoryProvider;
use Joomla\Component\Nxpeasyforms\Administrator\Ajax\Support\EmailSettingsRepository;
use Joomla\Component\Nxpeasyforms\Administrator\Ajax\Support\FormModelFactory;
use Joomla\Component\Nxpeasyforms\Administrator\Ajax\Support\FormOptionsNormalizer;
use Joomla\Component\Nxpeasyforms\Administrator\Ajax\Support\FormPayloadMapper;
use Joomla\Component\Nxpeasyforms\Administrator\Ajax\Support\MailchimpIntegrationService;
use Joomla\Component\Nxpeasyforms\Administrator\Ajax\Support\PermissionGuard;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Authentication\UserLoginHandler;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Email\EmailService;
use Joomla\Component\Nxpeasyforms\Administrator\Service\File\FileUploader;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations\GenericWebhookDispatcher;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations\HttpClient;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations\HubspotDispatcher;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations\IntegrationManager;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations\IntegrationQueue;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations\JoomlaArticleDispatcher;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations\MailchimpDispatcher;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations\MailchimpListsService;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations\SalesforceDispatcher;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations\SlackDispatcher;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations\WebhookDispatcher;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Registration\UserRegistrationHandler;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Export\SubmissionExporter;
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

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

return static function ($container): void {
    // Ensure language files are loaded for Text::_() calls in services
    try {
        $language = Factory::getApplication()->getLanguage();
        $language->load('com_nxpeasyforms', JPATH_SITE);
    } catch (\Throwable $e) {
        // Ignore errors during language loading
    }

    $container->share(
        PermissionGuard::class,
        static function (): PermissionGuard {
            return new PermissionGuard(Factory::getApplication());
        }
    );

    $container->share(
        FormModelFactory::class,
        static function (): FormModelFactory {
            return new FormModelFactory();
        }
    );

    $container->share(
        FormOptionsNormalizer::class,
        static function (): FormOptionsNormalizer {
            return new FormOptionsNormalizer();
        }
    );

    $container->share(
        FormPayloadMapper::class,
    static function ($container): FormPayloadMapper {
            return new FormPayloadMapper(
                $container->get(FormRepository::class),
                $container->get(FormOptionsNormalizer::class)
            );
        }
    );

    $container->share(
        CategoryProvider::class,
        static function ($container): CategoryProvider {
            return new CategoryProvider($container->get('Joomla\\Database\\DatabaseDriver'));
        }
    );

    $container->share(
        EmailSettingsRepository::class,
        static function (): EmailSettingsRepository {
            return new EmailSettingsRepository();
        }
    );

    $container->share(
        MailchimpIntegrationService::class,
    static function ($container): MailchimpIntegrationService {
            return new MailchimpIntegrationService(
                $container->get(FormRepository::class),
                $container->get(MailchimpListsService::class)
            );
        }
    );

    $container->share(
        FormRepository::class,
        static function ($container): FormRepository {
            return new FormRepository($container->get('Joomla\\Database\\DatabaseDriver'));
        }
    );

    $container->share(
        SubmissionRepository::class,
        static function ($container): SubmissionRepository {
            return new SubmissionRepository($container->get('Joomla\\Database\\DatabaseDriver'));
        }
    );

    $container->share(
        SubmissionExporter::class,
        static function ($container): SubmissionExporter {
            return new SubmissionExporter($container->get(SubmissionRepository::class));
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
    static function ($container): FieldValidator {
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
    static function ($container): FileUploader {
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
        MailchimpListsService::class,
    static function ($container): MailchimpListsService {
            return new MailchimpListsService($container->get(HttpClient::class));
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
    static function ($container): MessageFormatter {
            return new MessageFormatter($container->get(TemplateRenderer::class));
        }
    );

    $container->share(
        WebhookDispatcher::class,
    static function ($container): WebhookDispatcher {
            return new WebhookDispatcher(
                null,
                $container->get(HttpClient::class),
                null
            );
        }
    );

    $container->share(
        GenericWebhookDispatcher::class,
    static function ($container): GenericWebhookDispatcher {
            return new GenericWebhookDispatcher(
                $container->get(HttpClient::class)
            );
        }
    );

    $container->share(
        MailchimpDispatcher::class,
    static function ($container): MailchimpDispatcher {
            return new MailchimpDispatcher(
                $container->get(HttpClient::class),
                $container->get(TemplateRenderer::class),
                null
            );
        }
    );

    $container->share(
        HubspotDispatcher::class,
    static function ($container): HubspotDispatcher {
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
    static function ($container): SlackDispatcher {
            return new SlackDispatcher(
                $container->get(HttpClient::class),
                $container->get(MessageFormatter::class),
                $container->get(TemplateRenderer::class)
            );
        }
    );

    $container->share(
        SalesforceDispatcher::class,
    static function ($container): SalesforceDispatcher {
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
    static function ($container): UserRegistrationHandler {
                return new UserRegistrationHandler($container->get('Joomla\\Database\\DatabaseDriver'));
        }
    );

    $container->share(
        UserLoginHandler::class,
    static function ($container): UserLoginHandler {
                return new UserLoginHandler(null, $container->get('Joomla\\Database\\DatabaseDriver'));
        }
    );

    $container->share(
        IntegrationManager::class,
    static function ($container): IntegrationManager {
            $manager = new IntegrationManager();
            $manager->register('webhook', $container->get(WebhookDispatcher::class));
            $manager->register('joomla_article', $container->get(JoomlaArticleDispatcher::class));
            $manager->register('zapier', $container->get(GenericWebhookDispatcher::class));
            $manager->register('make', $container->get(GenericWebhookDispatcher::class));
            $manager->register('slack', $container->get(SlackDispatcher::class));
            $manager->register('mailchimp', $container->get(MailchimpDispatcher::class));
            $manager->register('hubspot', $container->get(HubspotDispatcher::class));
            $manager->register('salesforce', $container->get(SalesforceDispatcher::class));

            return $manager;
        }
    );

    $container->share(
        SubmissionService::class,
    static function ($container): SubmissionService {
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

    $container->share(
        FormAjaxHandler::class,
    static function ($container): FormAjaxHandler {
            return new FormAjaxHandler(
                $container->get(PermissionGuard::class),
                $container->get(FormModelFactory::class),
                $container->get(FormPayloadMapper::class)
            );
        }
    );

    $container->share(
        EmailAjaxHandler::class,
    static function ($container): EmailAjaxHandler {
            return new EmailAjaxHandler(
                $container->get(PermissionGuard::class),
                $container->get(EmailService::class),
                $container->get(FormRepository::class)
            );
        }
    );

    $container->share(
        EmailSettingsAjaxHandler::class,
    static function ($container): EmailSettingsAjaxHandler {
            return new EmailSettingsAjaxHandler(
                $container->get(PermissionGuard::class),
                $container->get(EmailSettingsRepository::class),
                $container->get(EmailService::class)
            );
        }
    );

    $container->share(
        JoomlaSettingsAjaxHandler::class,
    static function ($container): JoomlaSettingsAjaxHandler {
            return new JoomlaSettingsAjaxHandler(
                $container->get(PermissionGuard::class),
                $container->get(CategoryProvider::class)
            );
        }
    );

    $container->share(
        MailchimpAjaxHandler::class,
    static function ($container): MailchimpAjaxHandler {
            return new MailchimpAjaxHandler(
                $container->get(PermissionGuard::class),
                $container->get(MailchimpIntegrationService::class)
            );
        }
    );

    $container->share(
        AjaxRouter::class,
    static function ($container): AjaxRouter {
            return new AjaxRouter(
                $container->get(FormAjaxHandler::class),
                $container->get(EmailAjaxHandler::class),
                $container->get(EmailSettingsAjaxHandler::class),
                $container->get(JoomlaSettingsAjaxHandler::class),
                $container->get(MailchimpAjaxHandler::class)
            );
        }
    );
};
