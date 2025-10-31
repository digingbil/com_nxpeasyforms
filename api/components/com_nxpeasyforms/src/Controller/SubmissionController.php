<?php

declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Api\Controller;

use Joomla\CMS\Application\CMSWebApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\ApiController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Response\JsonResponse;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Exception\SubmissionException;
use Joomla\Component\Nxpeasyforms\Administrator\Service\SubmissionService;
use Joomla\Input\Input;

use function explode;
use function filter_var;
use function is_array;
use function trim;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Public-facing controller for processing form submissions.
 */
final class SubmissionController extends ApiController
{
    protected $contentType = 'submission';

    protected $default_view = 'submission';
    private SubmissionService $service;

    /**
     * SubmissionController constructor.
     *
     * @param   array                         $config    Controller configuration
     * @param   MVCFactoryInterface|null      $factory   MVC factory instance
     * @param   CMSWebApplicationInterface|null  $app    Application instance
     * @param   Input|null                    $input     Input object
     * @param   SubmissionService|null        $service   Submission service
     *
     * @throws  \Exception
     */
    public function __construct(
        $config = [],
        ?MVCFactoryInterface $factory = null,
        ?CMSWebApplicationInterface $app = null,
        ?Input $input = null,
        ?SubmissionService $service = null
    ) {
        parent::__construct($config, $factory, $app, $input);

        // Load frontend language file for user-facing messages
        $language = Factory::getApplication()->getLanguage();
        $language->load('com_nxpeasyforms', JPATH_SITE);

        $container = Factory::getContainer();

        if (!$container->has(SubmissionService::class)) {
            $registerDomainServices = include \JPATH_ADMINISTRATOR . '/components/com_nxpeasyforms/services/domain-services.php';
            $registerDomainServices($container);
        }

        $this->service = $service ?? $container->get(SubmissionService::class);
    }

    public function create(): void
    {
        $data = $this->input->json->getArray();

        if (!is_array($data) || empty($data)) {
            $data = $this->input->post->getArray();
        }

        $formId = (int) ($data['formId'] ?? $data['form_id'] ?? 0);

        if ($formId <= 0) {
            $formId = $this->input->getInt('form_id');
        }

        if ($formId <= 0) {
            $this->respond([
                'success' => false,
                'message' => Text::_('COM_NXPEASYFORMS_ERROR_FORM_NOT_FOUND'),
            ], 404, true);

            return;
        }

        $files = $this->input->files->getArray();

        $context = [
            'ip_address' => $this->detectIp(),
            'user_agent' => $this->input->server->getString('HTTP_USER_AGENT', ''),
            'skip_token_validation' => true,
        ];

        try {
            $result = $this->service->handle($formId, $data, $context, $files);
            $this->respond($result);

            return;
        } catch (SubmissionException $exception) {
            $code = $exception->getStatus() ?: 400;
            $this->respond([
                'success' => false,
                'message' => $exception->getMessage(),
                'errors' => $exception->getErrors(),
            ], $code, true);

            return;
        } catch (\Throwable $throwable) {
            Factory::getApplication()->getLogger()->error('NXP Easy Forms submission failed: ' . $throwable->getMessage());

            $this->respond([
                'success' => false,
                'message' => Text::_('COM_NXPEASYFORMS_ERROR_VALIDATION'),
            ], 500, true);

            return;
        }
    }

    private function detectIp(): string
    {
        $server = $this->input->server;
        $keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];

        foreach ($keys as $key) {
            $value = $server->get($key);

            if (!$value) {
                continue;
            }

            $parts = explode(',', (string) $value);
            $candidate = trim($parts[0]);

            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * Writes a JSON response to the API output buffer.
     *
     * @param   array<string, mixed>  $payload  Response payload.
     * @param   int                   $status   HTTP status code.
     * @param   bool                  $error    Flag indicating error state.
     *
     * @return  void
     */
    private function respond(array $payload, int $status = 200, bool $error = false): void
    {
        $response = new JsonResponse($payload, null, $error);
        $this->app->setHeader('status', (string) $status, true);
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $this->app->sendHeaders();
        echo (string) $response;
        $this->app->close();
    }
}
