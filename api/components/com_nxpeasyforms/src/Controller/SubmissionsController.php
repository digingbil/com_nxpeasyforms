<?php

declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Api\Controller;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\ApiController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Exception\SubmissionException;
use Joomla\Component\Nxpeasyforms\Administrator\Service\SubmissionService;

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
final class SubmissionsController extends ApiController
{
    protected $contentType = 'submissions';

    protected $default_view = 'submissions';
    private SubmissionService $service;

    public function __construct(SubmissionService $service = null, array $config = [])
    {
        parent::__construct($config);
        $this->service = $service ?? Factory::getContainer()->get(SubmissionService::class);
    }

    public function create()
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
            throw new \RuntimeException(Text::_('COM_NXPEASYFORMS_ERROR_FORM_NOT_FOUND'), 404);
        }

        $files = $this->input->files->getArray();

        $context = [
            'ip_address' => $this->detectIp(),
            'user_agent' => $this->input->server->getString('HTTP_USER_AGENT', ''),
        ];

        try {
            $result = $this->service->handle($formId, $data, $context, $files);
        } catch (SubmissionException $exception) {
            return new JsonResponse([
                'success' => false,
                'message' => $exception->getMessage(),
                'errors' => $exception->errors(),
            ], $exception->getCode() ?: 400);
        } catch (\Throwable $throwable) {
            Factory::getApplication()->getLogger()->error('NXP Easy Forms submission failed: ' . $throwable->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => Text::_('COM_NXPEASYFORMS_ERROR_VALIDATION'),
            ], 500);
        }

        return new JsonResponse($result);
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
}
