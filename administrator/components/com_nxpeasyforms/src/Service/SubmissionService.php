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


use Psr\SimpleCache\InvalidArgumentException;
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

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Orchestrates end-to-end processing for form submissions, handling validation,
 * file uploads, email notifications, database storage, and third-party integrations.
 * Acts as a centralized service to coordinate all aspects of form processing while
 * enforcing security measures like CSRF protection, honeypot fields, captcha verification
 * and rate limiting.
 * @since 1.0.0
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
	 * Processes the form submission with validation and security checks
	 *
	 * @param   array<string, mixed>  $requestData  Form submission data from the request
	 * @param   array<string, mixed>  $context      Contextual information like IP address and user agent
	 * @param   array<string, mixed>  $files        Uploaded files from the request
	 *
	 * @return array<string, mixed> Processed submission result containing:
	 *                              - success: boolean indicating if submission was successful
	 *                              - message: Success/error message to display
	 *                              - data: Sanitized submission data
	 *                              - uuid: Unique identifier for the submission
	 *                              - submission_id: Database ID if stored
	 *                              - meta: Additional metadata
	 *                              - email: Email notification results
	 *
	 * @throws SubmissionException|\JsonException If validation fails or other submission error occurs
	 * @since 1.0.0
	 */
	public function handle(int $formId, array $requestData, array $context = [], array $files = []): array {
		$form = $this->forms->find($formId);

		if ($form === null)
		{
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

            // Extract provider-specific credentials
            $providerConfig = is_array($captchaConfig[$captchaProvider] ?? null)
                ? $captchaConfig[$captchaProvider]
                : [];

            $this->captchaService->verify(
                $captchaProvider,
                $captchaToken,
                [
                    'site_key' => $providerConfig['site_key'] ?? '',
                    'secret_key' => $providerConfig['secret_key'] ?? '',
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

        [$sanitised, $errors, $fieldMeta] = $this->normalizeValidationResult($validationResult);

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
	 * Validates CSRF token for form submission. Checks if the token is valid for post
	 * requests unless token validation is explicitly skipped in the context.
	 *
	 * @param   array<string, mixed>  $context  Context array which may contain 'skip_token_validation' flag
	 *
	 * @throws SubmissionException If token validation fails
	 * @since 1.0.0
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
	 * Builds and returns the context array containing IP address and user agent information
	 *
	 * @param   array<string, mixed>  $context  Input context array that may contain 'ip_address' and 'user_agent'
	 *
	 * @return array<string, mixed> Context array with IP address and user agent information
	 * @since 1.0.0
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
	 * Verifies honeypot field is empty and checks for minimum submission time
	 *
	 * @param   array<string, mixed>  $requestData  The submission request data
	 * @param   int                   $formId       ID of the form being submitted
	 *
	 * @throws SubmissionException If spam detection triggered or submission too fast
	 * @since 1.0.0
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
	 * Filters and modifies the submission request data before validation and processing.
	 * Allows plugins to modify or enhance the request data through the event system.
	 *
	 * @param   array<string, mixed>  $requestData  The raw form submission data
	 * @param   int                   $formId       ID of the form being submitted
	 * @param   array<string, mixed>  $form         The form configuration array
	 * @param   array<string, mixed>  $context      Context data like IP address and user agent
	 *
	 * @return array<string, mixed> The filtered request data
	 * @since 1.0.0
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
	 * Filters and transforms the sanitized submission data. Allows plugins to modify the cleaned data
	 * through the event system before storage and processing.
	 *
	 * @param   array<string, mixed>  $sanitised    Sanitized form submission data
	 * @param   int                   $formId       ID of the form being submitted
	 * @param   array<string, mixed>  $form         The form configuration array
	 * @param   array<string, mixed>  $requestData  The original unfiltered request data
	 * @param   array<string, mixed>  $context      Context data like IP address and user agent
	 *
	 * @return array<string, mixed> The filtered sanitized submission data
	 * @since 1.0.0
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
	 * Processes validation result and returns:
	 * - Sanitized data array containing cleaned form submission values
	 * - Any validation error messages keyed by field name
	 * - Field metadata array with additional field-specific information
	 *
	 * @return array{0: array<string, mixed>, 1: array<string, string>, 2: array<int, array<string, mixed>>}
	 * @since 1.0.0
	 */
	private function normalizeValidationResult(ValidationResult $result): array
    {
        return [
            $result->getSanitisedData(),
            $result->getErrors(),
            $result->getFieldMeta(),
        ];
    }

	/**
	 * Processes validation result and returns the processed arrays containing
	 * sanitized data, validation errors, and field metadata.
	 *
	 * @param   array<int, array<string, mixed>>  $fields     Form field definitions
	 * @param   array<string, mixed>              $files      Uploaded files from request
	 * @param   array<string, mixed>              $sanitised  Sanitized form values
	 * @param   array<int, array<string, mixed>>  $fieldMeta  Additional field metadata
	 *
	 * @return array{0: array<string, mixed>, 1: array<string, string>, 2: array<int, array<string, mixed>>} Array containing:
	 *         - [0] Sanitized submission data
	 *         - [1] Validation error messages keyed by field name
	 *         - [2] Field metadata with additional field-specific info
	 * @since 1.0.0
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
	 * Updates field metadata with new file upload information and returns the modified array.
	 *
	 * @param   array<int, array<string, mixed>>  $fieldMeta  Existing field metadata array
	 * @param   string                            $name       Field name to update
	 * @param   string                            $value      Field value to set
	 * @param   array<string, mixed>              $meta       Additional metadata to merge
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>> Updated field metadata array
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
	 * Processes and dispatches form submission data to configured third-party integrations.
	 * Handles both webhook endpoints and custom integration providers, with optional queueing support.
	 *
	 * @param   array<string, mixed>              $options    Form configuration options containing integration settings
	 * @param   array<string, mixed>              $form       Complete form configuration data
	 * @param   array<string, mixed>              $payload    Sanitized form submission data to be sent
	 * @param   array<string, mixed>              $context    Contextual data like IP address and user agent
	 * @param   array<int, array<string, mixed>>  $fieldMeta  Additional metadata for form fields
	 *
	 * @return void
	 * @throws InvalidArgumentException
	 * @since 1.0.0
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

	/**
	 * Generates a UUID v4 (random) string with a specific bit pattern in version and variant fields.
	 * The UUID follows RFC 4122 format with 32 hex digits separated by hyphens.
	 *
	 * @return string A 36 character UUID string in format: xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
	 *                where x is any hexadecimal digit and y is one of 8,9,a,b
	 *
	 * @since 1.0.0
	 */
	private function generateUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /**
     * Dispatches an event with payload.
     * 
     * @param array<string, mixed> $payload
     * @since 1.0.0
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
     * Allow plugins to filter a value.
     * 
     * @param array<string, mixed> $context
     *
     * @return mixed
     * @since 1.0.0
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

	/**
	 * Generates a unique field name to be used as a honeypot field for a specific form.
	 *
	 * @param   int  $formId  The unique identifier of the form for which the honeypot field name is generated.
	 *
	 * @return string The generated honeypot field name.
	 * @since 1.0.0
	 */
    public static function honeypotFieldName(int $formId): string
    {
        $secret = self::secret();
        $hash = hash('sha256', $secret . '|nxp_easy_forms|h|' . $formId);

        return substr($hash, 5, 24);
    }

	/**
	 * Generates a unique field name to be used as a timestamp field for a specific form.
	 *
	 * @param   int  $formId  The unique identifier of the form for which the timestamp field name is generated.
	 *
	 * @return string The generated timestamp field name.
	 * @since 1.0.0
	 */
    public static function timestampFieldName(int $formId): string
    {
        $secret = self::secret();
        $hash = hash('sha256', $secret . '|nxp_easy_forms|t|' . $formId);

        return substr($hash, 11, 24);
    }

	/**
	 * Returns a secret key used for generating secure field names.
	 * Uses application secret if available, otherwise falls back to default value.
	 *
	 * @return string Secret key used for generating field names
	 * @throws \Exception
	 * @since 1.0.0
	 */
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
