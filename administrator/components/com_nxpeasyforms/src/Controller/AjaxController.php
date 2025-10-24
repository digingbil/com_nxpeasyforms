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

use function array_replace_recursive;
use function is_array;
use function is_numeric;
use function is_string;
use function rawurldecode;
use function trim;

/**
 * Internal AJAX controller for administrator requests.
 */
final class AjaxController extends BaseController
{
    public function route(): void
    {
        $app = Factory::getApplication();
        $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);

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
     * @param array<int, string> $segments
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
     * @param array<int,string> $segments
     */
    private function handleSettings(array $segments, string $method): JsonResponse
    {
        $section = $segments[1] ?? '';

        if ($section !== 'email') {
            throw new \RuntimeException(Text::_('JERROR_PAGE_NOT_FOUND'), 404);
        }

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
        $resolvedOptions['email_recipient'] = $recipient;
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

    private function getEmailSettings(): JsonResponse
    {
        $this->assertAuthorised('core.manage');

        $params = ComponentHelper::getParams('com_nxpeasyforms');
        $config = Factory::getConfig();

        $settings = [
            'from_name' => (string) $params->get('email_from_name', (string) $config->get('fromname')),
            'from_email' => (string) $params->get('email_from_address', (string) $config->get('mailfrom')),
            'recipient' => (string) $params->get('email_default_recipient', ''),
            'delivery' => $this->extractDeliverySettings($params, false),
        ];

        return new JsonResponse([
            'success' => true,
            'settings' => $settings,
        ]);
    }

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

    private function emailDiagnostics(): JsonResponse
    {
        $this->assertAuthorised('core.manage');

        return new JsonResponse([
            'success' => true,
            'diagnostics' => [
                'loaded' => true,
                'warningAt' => null,
                'wpMail' => [
                    'lastError' => null,
                    'lastSuccess' => null,
                ],
            ],
        ]);
    }

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
     * @param array<string, mixed> $delivery
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

    private function assertAuthorised(string $action): void
    {
        $user = Factory::getUser();

        if (!$user->authorise($action, 'com_nxpeasyforms')) {
            throw new \RuntimeException(Text::_('JGLOBAL_AUTH_ACCESS_DENIED'), 403);
        }
    }

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
     * @param array<string, mixed> $payload
     */
    private function mapPayloadToTable(array $payload, ?int $id = null): array
    {
        $config = is_array($payload['config'] ?? null) ? $payload['config'] : [];
        $fields = is_array($config['fields'] ?? null) ? $config['fields'] : [];
        $options = is_array($config['options'] ?? null) ? $config['options'] : [];

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
     * @param object|null $item
     *
     * @return array<string, mixed>
     */
    private function transformForm(?object $item): array
    {
        if ($item === null) {
            throw new \RuntimeException(Text::_('COM_NXPEASYFORMS_ERROR_FORM_NOT_FOUND'), 404);
        }

        $fields = is_array($item->fields ?? null) ? $item->fields : [];
        $settings = is_array($item->settings ?? null) ? $item->settings : [];

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
}
