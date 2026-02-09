<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Api\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\ApiController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\Component\Nxpeasyforms\Administrator\Helper\FormDefaults;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Email\EmailService;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Repository\FormRepository;

use function array_replace_recursive;
use function is_array;
use function is_numeric;
use function is_string;
use function trim;

/**
 * API controller for email-related utilities.
 */
final class EmailsController extends ApiController
{
    protected $contentType = 'emails';

    protected $default_view = 'emails';

    private EmailService $emails;

    private FormRepository $forms;

    public function __construct($config = [], EmailService $emails = null, FormRepository $forms = null)
    {
        parent::__construct($config);

        $container = Factory::getContainer();
        $this->emails = $emails ?? $container->get(EmailService::class);
        $this->forms = $forms ?? $container->get(FormRepository::class);
    }

    public function create()
    {
        $this->assertAuthorised();

        $id = $this->input->get('id');

        if (!is_string($id) || strtolower($id) !== 'test') {
            throw new \RuntimeException(Text::_('JERROR_PAGE_NOT_FOUND'), 404);
        }

        $payload = $this->input->json->getArray();

        $recipient = is_string($payload['recipient'] ?? null) ? trim($payload['recipient']) : '';

        if ($recipient === '') {
            throw new \RuntimeException(Text::_('COM_NXPEASYFORMS_EMAIL_NO_RECIPIENT'), 400);
        }

        $options = is_array($payload['options'] ?? null) ? $payload['options'] : [];

        $formId = (int) ($payload['formId'] ?? 0);
        if ($formId <= 0) {
            $formId = $this->input->getInt('formId');
        }
        $existing = $formId > 0 ? $this->forms->find($formId) : null;
        $baseOptions = is_array($existing['config']['options'] ?? null) ? $existing['config']['options'] : [];

        $defaults = FormDefaults::builderConfig()['options'];
        $resolvedOptions = array_replace_recursive($defaults, $baseOptions, $options);
        $resolvedOptions['email_recipient'] = $recipient;
        $resolvedOptions['send_email'] = true;

        $formTitle = $existing['title'] ?? Text::_('COM_NXPEASYFORMS_UNTITLED_FORM');

        $result = $this->emails->dispatchSubmission(
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

    private function assertAuthorised(): void
    {
        $user = $this->app->getIdentity();

        if (!$user->authorise('core.manage', 'com_nxpeasyforms')) {
            throw new \RuntimeException(Text::_('JGLOBAL_AUTH_ACCESS_DENIED'), 403);
        }
    }
}
