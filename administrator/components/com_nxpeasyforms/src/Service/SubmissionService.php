<?php

declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Service;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
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
use Joomla\Component\Nxpeasyforms\Administrator\Service\Validation\FieldValidator;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Validation\ValidationResult;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\Event;

use function bin2hex;
use function chr;
use function is_array;
use function is_string;
use function max;
use function ord;
use function random_bytes;
use function str_split;
use function substr;
use function trim;
use function hash;
use function time;
use function vsprintf;

/**
 * Orchestrates end-to-end processing for form submissions.
 */
final class SubmissionService
{
    private FormRepository $forms;

    private SubmissionRepository $submissions;

    private FieldValidator $fieldValidator;

    private CaptchaService $captchaService;

    private RateLimiter $rateLimiter;

    private IpHandler $ipHandler;

    private ?DispatcherInterface $dispatcher;

    private EmailService $emailService;

    private FileUploader $fileUploader;

    private IntegrationManager $integrationManager;

    private IntegrationQueue $integrationQueue;

    public function __construct(
        ?FormRepository $forms = null,
        ?SubmissionRepository $submissions = null,
        ?FieldValidator $fieldValidator = null,
        ?CaptchaService $captchaService = null,
        ?RateLimiter $rateLimiter = null,
        ?IpHandler $ipHandler = null,
        ?DispatcherInterface $dispatcher = null,
        ?FileUploader $fileUploader = null,
        ?EmailService $emailService = null,
        ?IntegrationManager $integrationManager = null,
        ?IntegrationQueue $integrationQueue = null
    ) {
        $container = Factory::getContainer();

        $this->forms = $forms ?? $container->get(FormRepository::class);
        $this->submissions = $submissions ?? $container->get(SubmissionRepository::class);
        $this->fieldValidator = $fieldValidator ?? $container->get(FieldValidator::class);
        $this->captchaService = $captchaService ?? $container->get(CaptchaService::class);
        $this->rateLimiter = $rateLimiter ?? $container->get(RateLimiter::class);
        $this->ipHandler = $ipHandler ?? $container->get(IpHandler::class);
        $this->dispatcher = $dispatcher ?? $this->detectDispatcher();
        $this->fileUploader = $fileUploader ?? $container->get(FileUploader::class);
        $this->emailService = $emailService ?? $container->get(EmailService::class);
        $this->integrationManager = $integrationManager ?? $container->get(IntegrationManager::class);
        $this->integrationQueue = $integrationQueue ?? $container->get(IntegrationQueue::class);
    }

    /**
     * @param array<string, mixed> $requestData
     * @param array<string, mixed> $context
     * @param array<string, mixed> $files
     *
     * @return array<string, mixed>
     *
     * @throws SubmissionException
     */
    public function handle(int $formId, array $requestData, array $context = [], array $files = []): array
    {
        $form = $this->forms->find($formId);

        if ($form === null) {
            throw new SubmissionException(Text::_('COM_NXPEASYFORMS_ERROR_FORM_NOT_FOUND'), 404);
        }

        if ((int) ($form['active'] ?? 1) !== 1) {
            throw new SubmissionException(Text::_('COM_NXPEASYFORMS_ERROR_FORM_INACTIVE'), 404);
        }

        $config = $form['config'] ?? ['fields' => [], 'options' => []];
        $fields = is_array($config['fields'] ?? null) ? $config['fields'] : [];
        $options = is_array($config['options'] ?? null) ? $config['options'] : [];

        $this->assertTokenValid($context);

        if (!empty($options['honeypot'])) {
            $this->verifyHoneypot($formId, $requestData);
        }

        $context = $this->buildContext($context);

        $captchaConfig = $options['captcha'] ?? [];
        $captchaProvider = isset($captchaConfig['provider']) && is_string($captchaConfig['provider'])
            ? $captchaConfig['provider']
            : 'none';

        if ($captchaProvider !== 'none') {
            $captchaToken = isset($requestData['_nxp_captcha_token'])
                ? trim((string) $requestData['_nxp_captcha_token'])
                : '';

            $this->captchaService->verify(
                $captchaProvider,
                $captchaToken,
                [
                    'site_key' => $captchaConfig['site_key'] ?? '',
                    'secret_key' => $captchaConfig['secret_key'] ?? '',
                    'ip' => $context['ip_address'],
                    'form_id' => $formId,
                ]
            );

            unset(
                $requestData['_nxp_captcha_token'],
                $requestData['_nxp_captcha_provider'],
                $requestData['cf-turnstile-response'],
                $requestData['frc-captcha-response'],
                $requestData['frc-captcha-solution']
            );
        }

        $requestData = $this->filterSubmissionRequest($requestData, $formId, $form, $context);

        $this->dispatchEvent('onNxpEasyFormsBeforeSubmission', [
            'formId' => $formId,
            'form' => $form,
            'request' => $requestData,
            'context' => $context,
        ]);

        $throttle = $options['throttle'] ?? [];
        $maxRequests = max(1, (int) ($throttle['max_requests'] ?? 3));
        $perSeconds = max(1, (int) ($throttle['per_seconds'] ?? 10));

        $this->rateLimiter->enforce($formId, $context['ip_address'], $maxRequests, $perSeconds);

        $storedIp = $this->ipHandler->formatForStorage(
            $context['ip_address'],
            isset($options['ip_storage']) ? (string) $options['ip_storage'] : 'full'
        );

        $validationResult = $this->fieldValidator->validateAll($fields, $requestData, $files);

        [$sanitised, $errors, $fieldMeta] = $this->normaliseValidationResult($validationResult);

        if (empty($errors)) {
            [$sanitised, $fileErrors, $fieldMeta] = $this->processFileUploads($fields, $files, $sanitised, $fieldMeta);
            if (!empty($fileErrors)) {
                $errors = $fileErrors;
            }
        }

        $sanitised = $this->filterSanitisedSubmission($sanitised, $formId, $form, $requestData, $context);

        if (!empty($errors)) {
            throw new SubmissionException(
                $options['error_message'] ?? Text::_('COM_NXPEASYFORMS_ERROR_VALIDATION'),
                422,
                [
                    'fields' => $errors,
                    'data' => $sanitised,
                ]
            );
        }

        $uuid = $this->generateUuid();

        $submissionId = null;

        if (!empty($options['store_submissions'])) {
            $submissionId = $this->submissions->create(
                $formId,
                $uuid,
                $sanitised,
                [
                    'status' => 'new',
                    'ip_address' => $storedIp ?? '',
                    'user_agent' => $context['user_agent'],
                ]
            );
        }

        $emailResult = $this->emailService->dispatchSubmission(
            $form,
            $sanitised,
            [
                'field_meta' => $fieldMeta,
                'ip_address' => $storedIp,
                'user_agent' => $context['user_agent'],
            ]
        );

        $this->dispatchIntegrations($options, $form, $sanitised, $context, $fieldMeta);

        $result = [
            'success' => true,
            'message' => $options['success_message'] ?? Text::_('COM_NXPEASYFORMS_MESSAGE_SUBMISSION_SUCCESS'),
            'data' => $sanitised,
            'uuid' => $uuid,
            'submission_id' => $submissionId,
            'meta' => [
                'ip_address' => $storedIp,
                'field_meta' => $fieldMeta,
            ],
            'email' => $emailResult,
        ];

        $this->dispatchEvent('onNxpEasyFormsAfterSubmission', [
            'formId' => $formId,
            'sanitised' => $sanitised,
            'result' => $result,
            'form' => $form,
            'context' => $context,
        ]);

        return $result;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @throws SubmissionException
     */
    private function assertTokenValid(array $context): void
    {
        if (!empty($context['skip_token_validation'])) {
            return;
        }

        if (!Session::checkToken('post')) {
            throw new SubmissionException(Text::_('COM_NXPEASYFORMS_ERROR_SECURITY_VERIFICATION'), 403);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContext(array $context): array
    {
        $ip = isset($context['ip_address']) && is_string($context['ip_address'])
            ? $context['ip_address']
            : $this->ipHandler->detect();

        $userAgent = isset($context['user_agent']) && is_string($context['user_agent'])
            ? $context['user_agent']
            : ($_SERVER['HTTP_USER_AGENT'] ?? '');

        return [
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ];
    }

    /**
     * @param array<string, mixed> $requestData
     *
     * @throws SubmissionException
     */
    private function verifyHoneypot(int $formId, array $requestData): void
    {
        $honeypotField = self::honeypotFieldName($formId);
        $timestampField = self::timestampFieldName($formId);

        $honeypot = isset($requestData[$honeypotField]) ? trim((string) $requestData[$honeypotField]) : '';

        if ($honeypot !== '') {
            throw new SubmissionException(Text::_('COM_NXPEASYFORMS_ERROR_SPAM_DETECTED'), 400);
        }

        $renderedAt = isset($requestData[$timestampField]) ? (int) $requestData[$timestampField] : 0;

        if ($renderedAt > 0) {
            $minimumElapsed = (int) $this->filterValue(
                'onNxpEasyFormsFilterMinSubmissionTime',
                2,
                ['formId' => $formId]
            );

            $elapsed = time() - $renderedAt;

            if ($elapsed >= 0 && $elapsed < $minimumElapsed) {
                throw new SubmissionException(Text::_('COM_NXPEASYFORMS_ERROR_SUBMISSION_TOO_FAST'), 400);
            }
        }
    }

    /**
     * @param array<string, mixed> $requestData
     * @param array<string, mixed> $form
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function filterSubmissionRequest(array $requestData, int $formId, array $form, array $context): array
    {
        $payload = [
            'data' => &$requestData,
            'formId' => $formId,
            'form' => $form,
            'context' => $context,
        ];

        $this->dispatchEvent('onNxpEasyFormsFilterSubmissionRequest', $payload);

        return $requestData;
    }

    /**
     * @param array<string, mixed> $sanitised
     * @param array<string, mixed> $form
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function filterSanitisedSubmission(
        array $sanitised,
        int $formId,
        array $form,
        array $requestData,
        array $context
    ): array {
        $payload = [
            'data' => &$sanitised,
            'formId' => $formId,
            'form' => $form,
            'request' => $requestData,
            'context' => $context,
        ];

        $this->dispatchEvent('onNxpEasyFormsFilterSanitizedSubmission', $payload);

        return $sanitised;
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, string>, 2: array<int, array<string, mixed>>}
     */
    private function normaliseValidationResult(ValidationResult $result): array
    {
        return [
            $result->getSanitisedData(),
            $result->getErrors(),
            $result->getFieldMeta(),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     * @param array<string, mixed> $files
     * @param array<string, mixed> $sanitised
     * @param array<int, array<string, mixed>> $fieldMeta
     *
     * @return array{0: array<string, mixed>, 1: array<string, string>, 2: array<int, array<string, mixed>>}
     */
    private function processFileUploads(
        array $fields,
        array $files,
        array $sanitised,
        array $fieldMeta
    ): array {
        $errors = [];

        foreach ($fields as $field) {
            $type = $field['type'] ?? 'text';
            $name = $field['name'] ?? null;

            if ($type !== 'file' || !is_string($name) || $name === '') {
                continue;
            }

            [$value, $meta, $error] = $this->fileUploader->handle($field, $files);

            if ($error !== null) {
                $errors[$name] = $error;
            }

            $sanitised[$name] = $value;
            $fieldMeta = $this->updateFieldMeta($fieldMeta, $name, $value, $meta);
        }

        return [$sanitised, $errors, $fieldMeta];
    }

    /**
     * @param array<int, array<string, mixed>> $fieldMeta
     * @param array<string, mixed> $meta
     *
     * @return array<int, array<string, mixed>>
     */
    private function updateFieldMeta(array $fieldMeta, string $name, string $value, array $meta): array
    {
        foreach ($fieldMeta as &$entry) {
            if (($entry['name'] ?? null) === $name) {
                $entry['value'] = $value;
                $entry['meta'] = $meta;
                break;
            }
        }

        unset($entry);

        return $fieldMeta;
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $form
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $context
     * @param array<int, array<string, mixed>> $fieldMeta
     */
    private function dispatchIntegrations(
        array $options,
        array $form,
        array $payload,
        array $context,
        array $fieldMeta
    ): void {
        $integrations = isset($options['integrations']) && is_array($options['integrations'])
            ? $options['integrations']
            : [];

        if (empty($integrations)) {
            // still allow standalone webhook configuration
            $integrations = [];
        }

        if (!empty($options['webhooks']['enabled'])) {
            $dispatcher = $this->integrationManager->get('webhook');
            if ($dispatcher !== null) {
                $dispatcher->dispatch($options['webhooks'], $form, $payload, $context, $fieldMeta);
            }
        }

        foreach ($integrations as $integrationId => $settings) {
            if (!is_array($settings) || empty($settings['enabled'])) {
                continue;
            }

            $dispatcher = $this->integrationManager->get($integrationId);

            if ($dispatcher === null) {
                $this->dispatchEvent('onNxpEasyFormsIntegrationDispatch', [
                    'integrationId' => $integrationId,
                    'settings' => $settings,
                    'form' => $form,
                    'payload' => $payload,
                    'context' => $context,
                    'field_meta' => $fieldMeta,
                ]);

                continue;
            }

            if ($this->integrationQueue->shouldQueue($integrationId)) {
                $this->integrationQueue->enqueue($integrationId, $settings, $form, $payload, $context, $fieldMeta);
                continue;
            }

            $dispatcher->dispatch($settings, $form, $payload, $context, $fieldMeta);
        }

        $this->integrationQueue->process($this->integrationManager);
    }

    private function generateUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function dispatchEvent(string $eventName, array $payload): void
    {
        if ($this->dispatcher === null) {
            return;
        }

        $event = new Event($eventName, $payload);
        $this->dispatcher->dispatch($event->getName(), $event);
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return mixed
     */
    private function filterValue(string $eventName, $value, array $context = [])
    {
        if ($this->dispatcher === null) {
            return $value;
        }

        $payload = ['value' => &$value] + $context;
        $event = new Event($eventName, $payload);
        $this->dispatcher->dispatch($event->getName(), $event);

        return $event['value'] ?? $value;
    }

    public static function honeypotFieldName(int $formId): string
    {
        $secret = self::secret();
        $hash = hash('sha256', $secret . '|nxp_easy_forms|h|' . $formId);

        return substr($hash, 5, 24);
    }

    public static function timestampFieldName(int $formId): string
    {
        $secret = self::secret();
        $hash = hash('sha256', $secret . '|nxp_easy_forms|t|' . $formId);

        return substr($hash, 11, 24);
    }

    private static function secret(): string
    {
        /** @var CMSApplicationInterface $app */
        $app = Factory::getApplication();
        $secret = (string) $app->get('secret');

        return $secret !== '' ? $secret : 'nxp_easy_forms';
    }

    private function detectDispatcher(): ?DispatcherInterface
    {
        try {
            return Factory::getApplication()->getDispatcher();
        } catch (\Throwable $exception) {
            return null;
        }
    }
}
