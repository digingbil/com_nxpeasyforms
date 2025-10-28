<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Controller;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Table\Table;
use Joomla\Component\Nxpeasyforms\Administrator\Helper\FormDefaults;
use Joomla\Component\Nxpeasyforms\Administrator\Model\FormModel as AdminFormModel;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Email\EmailService;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Repository\FormRepository;
use Joomla\Registry\Registry;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\ServiceProviderInterface;


use function array_key_exists;
use function array_replace_recursive;
use function is_array;
use function is_numeric;
use function is_string;
use function is_file;
use function rawurldecode;
use function strtolower;
use function trim;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Internal AJAX controller for administrator requests.
 */
final class AjaxController extends BaseController
{

	/**
	 * Route the request to the appropriate action.
	 *
	 * @throws \Exception
	 * @since 1.0.0
	 */
    public function route(): void
    {
        $app = Factory::getApplication();
        $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);

        $this->bootDomainServices();

        $path = trim(rawurldecode((string) $this->input->getString('path', '')), '/');
        $segments = $path === '' ? [] : explode('/', $path);
        $method = strtoupper($this->input->getMethod());

        try {
            $response = $this->dispatch($segments, $method);
        } catch (\Throwable $exception) {
            $status = (int) $exception->getCode();

            if ($status < 100 || $status >= 600) {
                $status = 500;
            }

            $response = new JsonResponse(
                [
                    'success' => false,
                    'message' => $exception->getMessage(),
                ],
                $status
            );
        }

        echo $response;
        $app->close();
    }

    /**
     * Make sure component service providers are registered before handling AJAX requests.
     *
     * @return void
     */
    private function bootDomainServices(): void
    {
        $container = Factory::getContainer();

        if ($container->has(EmailService::class) && $container->has(FormRepository::class)) {
            return;
        }

        $providerPath = \JPATH_ADMINISTRATOR . '/components/com_nxpeasyforms/services/provider.php';

        if (!is_file($providerPath)) {
            return;
        }

        $provider = require $providerPath;

        if ($provider instanceof ServiceProviderInterface) {
            $container->registerServiceProvider($provider);
        }
    }

    /**
     * Dispatch the request to the appropriate action.
     *
     * @param array<int, string> $segments
    * @param string $method The HTTP method used for the request (GET, POST, etc.).
    *
    * @return JsonResponse A JSON response representing the requested resource or error.
    *
    * @throws \RuntimeException When the requested resource cannot be found.
    * @since 1.0.0
     */
    private function dispatch(array $segments, string $method): JsonResponse
    {
        $resource = $segments[0] ?? '';

        return match ($resource) {
            'forms' => $this->handleForms($segments[1] ?? '', $method),
            'emails' => $this->handleEmails($segments[1] ?? '', $method),
            'settings' => $this->handleSettings($segments, $method),
            default => throw new \RuntimeException(Text::_('JERROR_PAGE_NOT_FOUND'), 404),
        };
    }

	/**
	 * Handles the forms resource.
	 *
     * @param string $action The action segment for forms (for example: 'get', 'save').
     * @param string $method The HTTP method used for the request.
     *
     * @return JsonResponse
     *
     * @since 1.0.0
	 */
    private function handleForms(string $action, string $method): JsonResponse
    {
        return match ($action) {
            'get' => $this->fetchForm(),
            'save' => $method === 'POST'
                ? $this->saveForm()
                : throw new \RuntimeException(Text::_('JERROR_PAGE_NOT_FOUND'), 404),
            default => throw new \RuntimeException(Text::_('JERROR_PAGE_NOT_FOUND'), 404),
        };
    }

    /**
     * Fetch a form and return a JSON response with the transformed item.
     *
     * This will check the session token and required permissions, then fetch
     * the requested form by id from the payload or query string.
     *
     * @return JsonResponse
     *
     * @throws \RuntimeException When the request is invalid, unauthorised or the form is not found.
     * @since 1.0.0
     */
    private function fetchForm(): JsonResponse
    {
        Session::checkToken('post');

        $payload = $this->input->json->getArray();

        if (!is_array($payload) || empty($payload)) {
            $payload = $this->input->post->getArray();
        }

        $formId = isset($payload['id']) ? (int) $payload['id'] : $this->input->getInt('id');

        if ($formId <= 0) {
            throw new \RuntimeException(Text::_('COM_NXPEASYFORMS_ERROR_FORM_NOT_FOUND'), 404);
        }

        $this->assertAuthorised('core.edit');

        $model = $this->getFormModel();
        $item = $model->getItem($formId);

        return new JsonResponse($this->transformForm($item));
    }

    /**
     * Save a form from the provided payload and return the saved representation.
     *
     * The method validates the session token and user permissions, maps the
     * incoming payload to table data and delegates saving to the model.
     *
     * @return JsonResponse
     *
     * @throws \RuntimeException When saving fails or the request is invalid.
     * @since 1.0.0
     */
    private function saveForm(): JsonResponse
    {
        Session::checkToken('post');

        $payload = $this->input->json->getArray();

        if (!is_array($payload)) {
            $payload = [];
        }

        $formId = isset($payload['id']) ? (int) $payload['id'] : 0;

        $this->assertAuthorised($formId > 0 ? 'core.edit' : 'core.create');

        $model = $this->getFormModel();
        $data = $this->mapPayloadToTable($payload, $formId > 0 ? $formId : null);

        if (!$model->save($data)) {
            throw new \RuntimeException($model->getError() ?: Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 400);
        }

        $savedId = (int) $model->getState('form.id');
        $savedId = $savedId > 0 ? $savedId : $formId;

        $item = $model->getItem($savedId);

        return new JsonResponse($this->transformForm($item));
    }

    /**
     * Handle email related actions over AJAX.
     *
     * @param string $action The email action to perform (e.g. 'test').
     * @param string $method The HTTP method used for the request.
     *
     * @return JsonResponse
     *
     * @since 1.0.0
     */
    private function handleEmails(string $action, string $method): JsonResponse
    {
        return match ($action) {
            'test' => $method === 'POST'
                ? $this->sendTestEmail()
                : throw new \RuntimeException(Text::_('JERROR_PAGE_NOT_FOUND'), 404),
            default => throw new \RuntimeException(Text::_('JERROR_PAGE_NOT_FOUND'), 404),
        };
    }

    /**
     * Handle settings resource routing.
     *
     * @param array<int,string> $segments The path segments for settings (section/action/...)
     * @param string $method The HTTP method used for the request.
     *
     * @return JsonResponse
     *
     * @since 1.0.0
     */
    private function handleSettings(array $segments, string $method): JsonResponse
    {
        $section = $segments[1] ?? '';

        return match ($section) {
            'email' => $this->handleEmailSettings($segments, $method),
            'joomla' => $this->handleJoomlaSettings($segments, $method),
            default => throw new \RuntimeException(Text::_('JERROR_PAGE_NOT_FOUND'), 404),
        };
    }

    /**
     * Handle the email settings subsection.
     *
     * @param array<int,string> $segments Path segments under settings/email.
     * @param string $method The HTTP method used for the request.
     *
     * @return JsonResponse
     *
     * @since 1.0.0
     */
    private function handleEmailSettings(array $segments, string $method): JsonResponse
    {
        $action = $segments[2] ?? '';

        return match ($action) {
            '' => $this->getEmailSettings(),
            'save' => $method === 'POST'
                ? $this->saveEmailSettings()
                : throw new \RuntimeException(Text::_('JERROR_PAGE_NOT_FOUND'), 404),
            'test' => $method === 'POST'
                ? $this->sendSettingsTestEmail()
                : throw new \RuntimeException(Text::_('JERROR_PAGE_NOT_FOUND'), 404),
            'diagnostics' => $this->emailDiagnostics(),
            default => throw new \RuntimeException(Text::_('JERROR_PAGE_NOT_FOUND'), 404),
        };
    }

    /**
     * Handle Joomla-specific settings subsections.
     *
     * @param array<int,string> $segments Path segments under settings/joomla.
     * @param string $method The HTTP method used for the request.
     *
     * @return JsonResponse
     *
     * @since 1.0.0
     */
    private function handleJoomlaSettings(array $segments, string $method): JsonResponse
    {
        $action = $segments[2] ?? '';

        return match ($action) {
            'categories' => $method === 'GET'
                ? $this->getJoomlaCategories()
                : throw new \RuntimeException(Text::_('JERROR_PAGE_NOT_FOUND'), 404),
            default => throw new \RuntimeException(Text::_('JERROR_PAGE_NOT_FOUND'), 404),
        };
    }

    /**
     * Send a one-off test email to a supplied recipient using the configured services.
     *
     * The request MUST be a POST and the user must have appropriate permissions.
     *
     * @return JsonResponse
     *
     * @throws \RuntimeException When recipient is missing or sending fails.
     * @since 1.0.0
     */
    private function sendTestEmail(): JsonResponse
    {
        Session::checkToken('post');
        $this->assertAuthorised('core.manage');

        $payload = $this->input->json->getArray();
        $recipient = is_string($payload['recipient'] ?? null)
            ? trim($payload['recipient'])
            : '';

        if ($recipient === '') {
            throw new \RuntimeException(Text::_('COM_NXPEASYFORMS_EMAIL_NO_RECIPIENT'), 400);
        }

        $options = is_array($payload['options'] ?? null) ? $payload['options'] : [];
        $formId = isset($payload['formId']) ? (int) $payload['formId'] : 0;

        $container = Factory::getContainer();
        $emailService = $container->get(EmailService::class);
        $formRepository = $container->get(FormRepository::class);

        $existing = $formId > 0 ? $formRepository->find($formId) : null;
        $baseOptions = is_array($existing['config']['options'] ?? null)
            ? $existing['config']['options']
            : [];
        $defaults = FormDefaults::builderConfig()['options'];

        $resolvedOptions = array_replace_recursive($defaults, $baseOptions, $options);
        if ($recipient !== '') {
            $resolvedOptions['email_recipient'] = $recipient;
        }
        $resolvedOptions['send_email'] = true;

        $formTitle = $existing['title'] ?? Text::_('COM_NXPEASYFORMS_UNTITLED_FORM');

        $result = $emailService->dispatchSubmission(
            [
                'id' => is_numeric($existing['id'] ?? null) ? (int) $existing['id'] : 0,
                'title' => $formTitle,
                'config' => [
                    'options' => $resolvedOptions,
                ],
            ],
            [
                'data' => [],
            ],
            [
                'field_meta' => [],
            ]
        );

        return new JsonResponse($result, $result['sent'] ? 200 : 500);
    }

    /**
     * Retrieve current email related component settings for the administrator UI.
     *
     * @return JsonResponse
     *
     * @since 1.0.0
     */
    private function getEmailSettings(): JsonResponse
    {
        $this->assertAuthorised('core.manage');

        $params = ComponentHelper::getParams('com_nxpeasyforms');
        $config = Factory::getConfig();

        // Handle nested params structure (same as EmailService does)
        if ($params->exists('params')) {
            $nested = $params->get('params');

            if ($nested instanceof Registry) {
                $params = clone $nested;
            } elseif (is_array($nested)) {
                $params = new Registry($nested);
            } elseif (is_string($nested)) {
                $decoded = json_decode($nested, true);

                if (is_array($decoded)) {
                    $params = new Registry($decoded);
                }
            }
        }

        $settings = [
            'from_name' => (string) $params->get('email_from_name', (string) $config->get('fromname')),
            'from_email' => (string) $params->get('email_from_address', (string) $config->get('mailfrom')),
            'recipient' => (string) $params->get('email_default_recipient', (string) $config->get('mailfrom')),
            'delivery' => $this->extractDeliverySettings($params, false),
        ];

        return new JsonResponse([
            'success' => true,
            'settings' => $settings,
        ]);
    }

    /**
     * Return a list of Joomla content categories suitable for selection in the UI.
     *
     * @return JsonResponse
     *
     * @since 1.0.0
     */
    private function getJoomlaCategories(): JsonResponse
    {
        $this->assertAuthorised('core.manage');

        try {
            $categories = $this->fetchContentCategories();
        } catch (\RuntimeException $exception) {
            return new JsonResponse(
                [
                    'success' => false,
                    'message' => $exception->getMessage(),
                ],
                500
            );
        }

        return new JsonResponse(
            [
                'success' => true,
                'categories' => $categories,
            ]
        );
    }

    /**
     * Fetch content categories from the database and format them for client use.
     *
     * @return array<int, array<string, mixed>> An array of category rows with id and title keys.
     *
     * @throws \RuntimeException When the categories cannot be loaded from the database.
     * @since 1.0.0
     */
    private function fetchContentCategories(): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select(
                [
                    $db->quoteName('id'),
                    $db->quoteName('title'),
                    $db->quoteName('level'),
                ]
            )
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('extension') . ' = :extension')
            ->where($db->quoteName('published') . ' != -2')
            ->order($db->quoteName('lft') . ' ASC')
            ->bind(':extension', 'com_content');

        try {
            $db->setQuery($query);
            $rows = (array) $db->loadAssocList();
        } catch (\RuntimeException $exception) {
            throw new \RuntimeException(
                Text::_('COM_NXPEASYFORMS_ERROR_CATEGORIES_LOAD_FAILED'),
                500,
                $exception
            );
        }

        $formatted = [];

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);

            if ($id <= 0) {
                continue;
            }

            $level = max(0, (int) ($row['level'] ?? 0) - 1);
            $prefix = str_repeat('— ', $level);

            $formatted[] = [
                'id' => $id,
                'title' => $prefix . (string) ($row['title'] ?? Text::_('JGLOBAL_CATEGORY_UNKNOWN')),
            ];
        }

        return $formatted;
    }

    /**
     * Persist email settings for the component from the provided payload.
     *
     * Requires a valid session token and administrative permissions.
     *
     * @return JsonResponse
     *
     * @throws \RuntimeException When the payload is invalid or storage fails.
     * @since 1.0.0
     */
    private function saveEmailSettings(): JsonResponse
    {
        Session::checkToken('post');
        $this->assertAuthorised('core.admin');

        $payload = $this->input->json->getArray();

        if (!is_array($payload)) {
            throw new \RuntimeException(Text::_('JERROR_INPUT_DATA_INVALID'), 400);
        }

        $component = ComponentHelper::getComponent('com_nxpeasyforms');
        $params = new Registry($component->params);

        $params->set('email_from_name', (string) ($payload['from_name'] ?? ''));
        $params->set('email_from_address', (string) ($payload['from_email'] ?? ''));
        $params->set('email_default_recipient', (string) ($payload['recipient'] ?? ''));

        $delivery = is_array($payload['delivery'] ?? null) ? $payload['delivery'] : [];
        $this->applyDeliverySettings($params, $delivery);

        $this->storeComponentParams((int) $component->id, $params);

        return new JsonResponse(['success' => true]);
    }

    /**
     * Return a lightweight diagnostics report for email subsystems used by the component.
     *
     * @return JsonResponse
     *
     * @since 1.0.0
     */
    private function emailDiagnostics(): JsonResponse
    {
        $this->assertAuthorised('core.manage');

        return new JsonResponse([
            'success' => true,
            'diagnostics' => [
                'loaded' => true,
                'warningAt' => null,
                'mailer' => [
                    'lastError' => null,
                    'lastSuccess' => null,
                ],
            ],
        ]);
    }

    /**
     * Send a test email using the saved email settings in component configuration.
     *
     * This differs from {@see sendTestEmail} in that it uses component-level
     * configuration values rather than ad-hoc options passed in the request.
     *
     * @return JsonResponse
     *
     * @throws \RuntimeException When the recipient is missing or sending fails.
     * @since 1.0.0
     */
    private function sendSettingsTestEmail(): JsonResponse
    {
        Session::checkToken('post');
        $this->assertAuthorised('core.manage');

        $payload = $this->input->json->getArray();
        $recipient = is_string($payload['recipient'] ?? null)
            ? trim($payload['recipient'])
            : '';

        if ($recipient === '') {
            throw new \RuntimeException(Text::_('COM_NXPEASYFORMS_EMAIL_NO_RECIPIENT'), 400);
        }

        $component = ComponentHelper::getComponent('com_nxpeasyforms');
        $params = new Registry($component->params);
        $config = Factory::getConfig();

        $defaults = FormDefaults::builderConfig()['options'];
        $delivery = $this->extractDeliverySettings($params, true);

        $options = array_replace_recursive($defaults, [
            'send_email' => true,
            'email_recipient' => $recipient,
            'email_subject' => (string) $params->get('email_subject', $defaults['email_subject']),
            'email_from_name' => (string) $params->get('email_from_name', (string) $config->get('fromname')),
            'email_from_address' => (string) $params->get('email_from_address', (string) $config->get('mailfrom')),
            'email_reply_to' => (string) $params->get('email_reply_to', ''),
            'email_delivery' => $delivery,
        ]);

        $container = Factory::getContainer();
        $emailService = $container->get(EmailService::class);

        $result = $emailService->dispatchSubmission(
            [
                'id' => 0,
                'title' => Text::_('COM_NXPEASYFORMS'),
                'config' => [
                    'options' => $options,
                ],
            ],
            [
                'data' => [],
            ],
            [
                'field_meta' => [],
            ]
        );

        return new JsonResponse($result, $result['sent'] ? 200 : 500);
    }

    /**
     * Extract delivery/provider configuration from the component params.
     *
     * @param Registry $params       Component params container.
     * @param bool     $includeSecrets Whether secret keys should be returned or masked.
     *
     * @return array<string,mixed> Delivery configuration array suitable for the client.
     *
     * @since 1.0.0
     */
    private function extractDeliverySettings(Registry $params, bool $includeSecrets): array
    {
        $config = Factory::getConfig();

        $valueOrMask = static function (string $value, bool $include) {
            return $include ? $value : '';
        };

        $sendgridKey = (string) $params->get('email_sendgrid_key', '');
        $smtp2goKey = (string) $params->get('email_smtp2go_key', '');
        $mailgunKey = (string) $params->get('email_mailgun_key', '');
        $postmarkToken = (string) $params->get('email_postmark_api_token', '');
        $brevoKey = (string) $params->get('email_brevo_api_key', '');
        $sesAccess = (string) $params->get('email_amazon_ses_access_key', '');
        $sesSecret = (string) $params->get('email_amazon_ses_secret_key', '');
        $smtpPassword = (string) $params->get('email_smtp_password', '');

        return [
            'provider' => (string) $params->get('email_provider', 'joomla'),
            'sendgrid' => [
                'api_key' => $valueOrMask($sendgridKey, $includeSecrets),
                'api_key_set' => $sendgridKey !== '',
            ],
            'mailgun' => [
                'api_key' => $valueOrMask($mailgunKey, $includeSecrets),
                'api_key_set' => $mailgunKey !== '',
                'domain' => (string) $params->get('email_mailgun_domain', ''),
                'region' => (string) $params->get('email_mailgun_region', 'us'),
            ],
            'postmark' => [
                'api_token' => $valueOrMask($postmarkToken, $includeSecrets),
                'api_token_set' => $postmarkToken !== '',
            ],
            'brevo' => [
                'api_key' => $valueOrMask($brevoKey, $includeSecrets),
                'api_key_set' => $brevoKey !== '',
            ],
            'amazon_ses' => [
                'access_key' => $valueOrMask($sesAccess, $includeSecrets),
                'secret_key' => $valueOrMask($sesSecret, $includeSecrets),
                'access_key_set' => $sesAccess !== '',
                'secret_key_set' => $sesSecret !== '',
                'region' => (string) $params->get('email_amazon_ses_region', 'us-east-1'),
            ],
            'mailpit' => [
                'host' => (string) $params->get('email_mailpit_host', '127.0.0.1'),
                'port' => (int) $params->get('email_mailpit_port', 1025),
            ],
            'smtp2go' => [
                'api_key' => $valueOrMask($smtp2goKey, $includeSecrets),
                'api_key_set' => $smtp2goKey !== '',
            ],
            'smtp' => [
                'host' => (string) $params->get('email_smtp_host', ''),
                'port' => (int) $params->get('email_smtp_port', 587),
                'encryption' => (string) $params->get('email_smtp_encryption', 'tls'),
                'username' => (string) $params->get('email_smtp_username', ''),
                'password' => $valueOrMask($smtpPassword, $includeSecrets),
                'password_set' => $smtpPassword !== '',
            ],
        ];
    }

    /**
     * Apply delivery configuration values into the component parameter registry.
     *
     * Only non-empty secret values will overwrite existing values; empty secrets
     * will preserve existing settings unless explicitly cleared.
     *
     * @param Registry $params    Component params container.
     * @param array<string,mixed> $delivery Delivery configuration to apply.
     *
     * @return void
     * @since 1.0.0
     */
    private function applyDeliverySettings(Registry $params, array $delivery): void
    {
        $params->set('email_provider', (string) ($delivery['provider'] ?? 'joomla'));

        if (isset($delivery['sendgrid']) && is_array($delivery['sendgrid'])) {
            $key = trim((string) ($delivery['sendgrid']['api_key'] ?? ''));

            if ($key !== '') {
                $params->set('email_sendgrid_key', $key);
            }
        }

        if (isset($delivery['smtp2go']) && is_array($delivery['smtp2go'])) {
            $key = trim((string) ($delivery['smtp2go']['api_key'] ?? ''));

            if ($key !== '') {
                $params->set('email_smtp2go_key', $key);
            }
        }

        if (isset($delivery['mailgun']) && is_array($delivery['mailgun'])) {
            $params->set('email_mailgun_domain', (string) ($delivery['mailgun']['domain'] ?? ''));
            $params->set('email_mailgun_region', (string) ($delivery['mailgun']['region'] ?? 'us'));

            $key = trim((string) ($delivery['mailgun']['api_key'] ?? ''));

            if ($key !== '') {
                $params->set('email_mailgun_key', $key);
            }
        }

        if (isset($delivery['postmark']) && is_array($delivery['postmark'])) {
            $token = trim((string) ($delivery['postmark']['api_token'] ?? ''));

            if ($token !== '') {
                $params->set('email_postmark_api_token', $token);
            }
        }

        if (isset($delivery['brevo']) && is_array($delivery['brevo'])) {
            $key = trim((string) ($delivery['brevo']['api_key'] ?? ''));

            if ($key !== '') {
                $params->set('email_brevo_api_key', $key);
            }
        }

        if (isset($delivery['amazon_ses']) && is_array($delivery['amazon_ses'])) {
            $access = trim((string) ($delivery['amazon_ses']['access_key'] ?? ''));
            $secret = trim((string) ($delivery['amazon_ses']['secret_key'] ?? ''));

            if ($access !== '') {
                $params->set('email_amazon_ses_access_key', $access);
            }

            if ($secret !== '') {
                $params->set('email_amazon_ses_secret_key', $secret);
            }

            $params->set('email_amazon_ses_region', (string) ($delivery['amazon_ses']['region'] ?? 'us-east-1'));
        }

        if (isset($delivery['mailpit']) && is_array($delivery['mailpit'])) {
            $params->set('email_mailpit_host', (string) ($delivery['mailpit']['host'] ?? '127.0.0.1'));
            $params->set('email_mailpit_port', (int) ($delivery['mailpit']['port'] ?? 1025));
        }

        if (isset($delivery['smtp']) && is_array($delivery['smtp'])) {
            $params->set('email_smtp_host', (string) ($delivery['smtp']['host'] ?? ''));
            $params->set('email_smtp_port', (int) ($delivery['smtp']['port'] ?? 587));
            $params->set('email_smtp_encryption', (string) ($delivery['smtp']['encryption'] ?? 'tls'));
            $params->set('email_smtp_username', (string) ($delivery['smtp']['username'] ?? ''));

            $password = (string) ($delivery['smtp']['password'] ?? '');

            if ($password !== '') {
                $params->set('email_smtp_password', $password);
            } elseif (empty($delivery['smtp']['password_set'])) {
                $params->set('email_smtp_password', '');
            }
        }
    }

    /**
     * Store the component's params string into the extensions table for the provided extension id.
     *
     * @param int $extensionId The extension (component) id in the #__extensions table.
     * @param Registry $params The params registry to persist.
     *
     * @return void
     *
     * @throws \RuntimeException When the extension cannot be loaded or saving fails.
     * @since 1.0.0
     */
    private function storeComponentParams(int $extensionId, Registry $params): void
    {
        $table = Table::getInstance('extension');

        if (!$table->load($extensionId)) {
            throw new \RuntimeException(Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 500);
        }

        $data = [
            'extension_id' => $extensionId,
            'params' => $params->toString(),
        ];

        if (!$table->bind($data) || !$table->store()) {
            throw new \RuntimeException(Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 500);
        }
    }

    /**
     * Assert that the current user has the given permission for this component.
     *
     * @param string $action The action to check (for example: 'core.edit').
     *
     * @return void
     *
     * @throws \RuntimeException When the user is not authorised.
     * @since 1.0.0
     */
    private function assertAuthorised(string $action): void
    {
        $user = $this->app->getIdentity();

        if (!$user->authorise($action, 'com_nxpeasyforms')) {
            throw new \RuntimeException(Text::_('JGLOBAL_AUTH_ACCESS_DENIED'), 403);
        }
    }

	/**
	 * Create and return an instance of the administrator form model.
	 *
	 * @return AdminFormModel
	 *
	 * @throws \Exception
	 * @since 1.0.0
	 */
    private function getFormModel(): AdminFormModel
    {
        $factory = Factory::getApplication()
            ->bootComponent('com_nxpeasyforms')
            ->getMVCFactory();

        /** @var AdminFormModel $model */
        $model = $factory->createModel('Form', 'Administrator', ['ignore_request' => true]);

        return $model;
    }

    /**
     * Map an incoming JSON payload to the table data structure used by the model.
     *
     * @param array<string,mixed> $payload Incoming payload data from the request.
     * @param int|null $id Optional id for an existing record when updating.
     *
     * @return array<string,mixed> Data structure suitable for model save.
     * @since 1.0.0
     */
    private function mapPayloadToTable(array $payload, ?int $id = null): array
    {
        $config = is_array($payload['config'] ?? null) ? $payload['config'] : [];
        $fields = is_array($config['fields'] ?? null) ? $config['fields'] : [];
        $options = is_array($config['options'] ?? null) ? $config['options'] : [];

        $options = $this->normalizeOptionsForStorage($options);

        $data = [
            'title' => is_string($payload['title'] ?? null) ? trim($payload['title']) : '',
            'fields' => $fields,
            'settings' => $options,
        ];

        if (isset($payload['active'])) {
            $data['active'] = (int) (!empty($payload['active']));
        }

        if ($id !== null) {
            $data['id'] = $id;
        }

        return $data;
    }

    /**
     * Transform a model item into the API payload expected by the client.
     *
     * @param object|null $item The model item returned by the model's getItem method.
     *
     * @return array<string,mixed>
     *
     * @throws \RuntimeException When the provided item is null.
     * @since 1.0.0
     */
    private function transformForm(?object $item): array
    {
        if ($item === null) {
            throw new \RuntimeException(Text::_('COM_NXPEASYFORMS_ERROR_FORM_NOT_FOUND'), 404);
        }

        $fields = is_array($item->fields ?? null) ? $item->fields : [];
        $settings = is_array($item->settings ?? null) ? $item->settings : [];
        $settings = $this->normalizeOptionsForClient($settings);

        return [
            'id' => (int) ($item->id ?? 0),
            'title' => (string) ($item->title ?? Text::_('COM_NXPEASYFORMS_UNTITLED_FORM')),
            'active' => (int) ($item->active ?? 1),
            'config' => [
                'fields' => $fields,
                'options' => $settings,
            ],
            'created_at' => $item->created_at ?? null,
            'updated_at' => $item->updated_at ?? null,
        ];
    }

    /**
     * Normalize options stored in the form configuration for persistent storage.
     *
     * @param array<string,mixed> $options Options coming from the client.
     *
     * @return array<string,mixed> Normalized options suitable for storage.
     * @since 1.0.0
     */
    private function normalizeOptionsForStorage(array $options): array
    {
        $options['email_delivery'] = $this->normalizeEmailDelivery(
            is_array($options['email_delivery'] ?? null) ? $options['email_delivery'] : []
        );

        $integrations = is_array($options['integrations'] ?? null) ? $options['integrations'] : [];
        $integrations = $this->normalizeIntegrations($integrations);
        $options['integrations'] = $integrations;

        return $options;
    }

    /**
     * Normalize stored options for delivery to the client.
     *
     * @param array<string,mixed> $options Stored options.
     *
     * @return array<string,mixed> Normalized options for client consumption.
     * @since 1.0.0
     */
    private function normalizeOptionsForClient(array $options): array
    {
        return $this->normalizeOptionsForStorage($options);
    }

    /**
     * Ensure the email delivery configuration contains required keys and defaults.
     *
     * @param array<string,mixed> $delivery Delivery configuration from options.
     *
     * @return array<string,mixed> Normalized delivery configuration.
     * @since 1.0.0
     */
    private function normalizeEmailDelivery(array $delivery): array
    {
        $provider = isset($delivery['provider']) ? (string) $delivery['provider'] : 'joomla';

        $delivery['provider'] = $provider ?: 'joomla';

        $defaults = [
            'sendgrid' => [
                'api_key' => '',
            ],
            'mailgun' => [
                'api_key' => '',
                'domain' => '',
                'region' => 'us',
            ],
            'postmark' => [
                'api_token' => '',
            ],
            'brevo' => [
                'api_key' => '',
            ],
            'amazon_ses' => [
                'access_key' => '',
                'secret_key' => '',
                'region' => 'us-east-1',
            ],
            'mailpit' => [
                'host' => '127.0.0.1',
                'port' => 1025,
            ],
            'smtp2go' => [
                'api_key' => '',
            ],
            'smtp' => [
                'host' => '',
                'port' => 587,
                'encryption' => 'tls',
                'username' => '',
                'password' => '',
                'password_set' => false,
            ],
        ];

        foreach ($defaults as $key => $values) {
            $delivery[$key] = isset($delivery[$key]) && is_array($delivery[$key]) ? $delivery[$key] : [];

            foreach ($values as $field => $defaultValue) {
                if (!array_key_exists($field, $delivery[$key]) || $delivery[$key][$field] === null) {
                    $delivery[$key][$field] = $defaultValue;
                    continue;
                }

                if (is_string($delivery[$key][$field])) {
                    $delivery[$key][$field] = trim($delivery[$key][$field]);
                }

                if ($field === 'port') {
                    $delivery[$key][$field] = (int) $delivery[$key][$field] ?: $defaultValue;
                }
            }

            if ($key === 'mailgun') {
                $delivery[$key]['region'] = strtolower($delivery[$key]['region'] ?: 'us');
            }

            if ($key === 'amazon_ses') {
                $delivery[$key]['region'] = strtolower($delivery[$key]['region'] ?: 'us-east-1');
            }

            if ($key === 'mailpit') {
                $delivery[$key]['port'] = (int) ($delivery[$key]['port'] ?? 1025) ?: 1025;
            }

            if ($key === 'smtp') {
                $delivery[$key]['port'] = (int) ($delivery[$key]['port'] ?? 587) ?: 587;
                $delivery[$key]['password_set'] = !empty($delivery[$key]['password_set']);
            }
        }

        return $delivery;
    }

    /**
     * Normalize integrations configuration and remove unsupported integrations.
     *
     * @param array<string,mixed> $integrations Integrations configuration array.
     *
     * @return array<string,mixed> Normalized integrations array.
     * @since 1.0.0
     */
    private function normalizeIntegrations(array $integrations): array
    {
        if (isset($integrations['joomla_article']) && is_array($integrations['joomla_article'])) {
            $integrations['joomla_article'] = $this->normalizeArticleIntegration($integrations['joomla_article']);
        }

        unset($integrations['woocommerce']);

        return $integrations;
    }

    /**
     * Convert legacy article integration settings into the expected shape.
     *
     * @param array<string,mixed> $settings Legacy article integration settings.
     *
     * @return array<string,mixed> Converted article integration settings.
     * @since 1.0.0
     */
    private function normalizeArticleIntegration(array $settings): array
    {
        $map = is_array($settings['map'] ?? null) ? $settings['map'] : [];
        $map = array_merge(
            [
                'title' => '',
                'introtext' => '',
                'fulltext' => '',
                'tags' => '',
                'alias' => '',
                'featured_image' => '',
                'featured_image_alt' => '',
                'featured_image_caption' => '',
            ],
            $map
        );

        // Preserve featured image information in case we later support media handling.
        if (($map['featured_image'] ?? '') !== '' && !isset($map['media'])) {
            $map['media'] = [
                'featured_image' => $map['featured_image'],
            ];
        }

        $converted = [
            'enabled' => !empty($settings['enabled']),
            'category_id' => $this->parseCategoryId($settings),
            'status' => (string) ($settings['post_status'] ?? 'unpublished'),
            'author_mode' => (string) ($settings['author_mode'] ?? 'current_user'),
            'fixed_author_id' => (int) ($settings['fixed_author_id'] ?? 0),
            'language' => (string) ($settings['language'] ?? '*'),
            'access' => (int) ($settings['access'] ?? 1),
            'map' => $map,
        ];

        $tagsField = $this->extractLegacyTagsField($settings);

        if ($tagsField !== '') {
            $converted['map']['tags'] = $tagsField;
        }

        return $converted;
    }

    /**
     * Parse and return an integer category id from various possible payload shapes.
     *
     * @param array<string,mixed> $settings Integration or article settings that may contain category identifiers.
     *
     * @return int The resolved category id or 0 when none could be determined.
     * @since 1.0.0
     */
    private function parseCategoryId(array $settings): int
    {
        if (isset($settings['category_id'])) {
            return (int) $settings['category_id'];
        }

        $postType = $settings['post_type'] ?? '';

        if (is_numeric($postType)) {
            return (int) $postType;
        }

        return 0;
    }

    /**
     * Extract a legacy tags field mapping from older payloads.
     *
     * @param array<string,mixed> $settings The settings array potentially containing legacy taxonomies.
     *
     * @return string The field name mapped to tags, or an empty string when none found.
     * @since 1.0.0
     */
    private function extractLegacyTagsField(array $settings): string
    {
        // Legacy WordPress payloads exposed a generic "taxonomies" array; we only carry through tag mappings.
        $legacyTaxonomies = is_array($settings['taxonomies'] ?? null) ? $settings['taxonomies'] : [];

        foreach ($legacyTaxonomies as $taxonomy) {
            if (!is_array($taxonomy)) {
                continue;
            }

            $name = (string) ($taxonomy['taxonomy'] ?? '');

            if ($name !== 'post_tag' && $name !== 'tags') {
                continue;
            }

            $field = (string) ($taxonomy['field'] ?? '');

            if ($field !== '') {
                return $field;
            }
        }

        return '';
    }
}
