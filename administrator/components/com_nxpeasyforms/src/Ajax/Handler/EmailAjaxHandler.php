<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Ajax\Handler;

use Joomla\CMS\Language\Text;
use Joomla\Component\Nxpeasyforms\Administrator\Ajax\AjaxRequestContext;
use Joomla\Component\Nxpeasyforms\Administrator\Ajax\AjaxResult;
use Joomla\Component\Nxpeasyforms\Administrator\Ajax\Support\PermissionGuard;
use Joomla\Component\Nxpeasyforms\Administrator\Helper\FormDefaults;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Email\EmailService;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Repository\FormRepository;
use RuntimeException;
use function array_replace_recursive;
use function call_user_func;
use function is_array;
use function is_numeric;
use function is_string;
use function trim;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Handles email-related AJAX operations for administrator requests.
 */
final class EmailAjaxHandler
{
    /**
     * @var PermissionGuard
     */
    private PermissionGuard $guard;

    /**
     * @var EmailService
     */
    private EmailService $emailService;

    /**
     * @var FormRepository
     */
    private FormRepository $forms;

    /**
     * @param PermissionGuard $guard Performs ACL assertions.
     * @param EmailService $emailService Service used to dispatch submission emails.
     * @param FormRepository $forms Repository used to load stored form configuration.
     */
    public function __construct(PermissionGuard $guard, EmailService $emailService, FormRepository $forms)
    {
        $this->guard = $guard;
        $this->emailService = $emailService;
        $this->forms = $forms;
    }

    /**
     * Dispatch an email-related AJAX request.
     *
     * @param AjaxRequestContext $context Current AJAX request context.
     * @param string $action Action segment under the emails resource.
     * @param string $method HTTP method used for the request.
     *
     * @return AjaxResult
     */
    public function dispatch(AjaxRequestContext $context, string $action, string $method): AjaxResult
    {
        return match ($action) {
            'test' => $method === 'POST'
                ? $this->sendTestEmail($context)
                : throw new RuntimeException(Text::_('JERROR_PAGE_NOT_FOUND'), 404),
            default => throw new RuntimeException(Text::_('JERROR_PAGE_NOT_FOUND'), 404),
        };
    }

    /**
     * Send a one-off test email using the provided options.
     *
     * @param AjaxRequestContext $context Current AJAX request context.
     *
     * @return AjaxResult
     */
    private function sendTestEmail(AjaxRequestContext $context): AjaxResult
    {
        call_user_func(['Joomla\\CMS\\Session\\Session', 'checkToken'], 'post');
        $this->guard->assertAuthorised('core.manage');

        $input = $context->getInput();
        $payload = $input->json->getArray();
        $recipient = is_string($payload['recipient'] ?? null)
            ? trim($payload['recipient'])
            : '';

        if ($recipient === '') {
            throw new RuntimeException(Text::_('COM_NXPEASYFORMS_EMAIL_NO_RECIPIENT'), 400);
        }

        $options = is_array($payload['options'] ?? null) ? $payload['options'] : [];
        $formId = isset($payload['formId']) ? (int) $payload['formId'] : 0;

        $existing = null;

        if ($formId > 0) {
            $candidate = $this->forms->find($formId);
            $existing = is_array($candidate) ? $candidate : null;
        }

        $baseOptions = [];

        if (is_array($existing)) {
            $config = $existing['config'] ?? null;
            $optionsConfig = is_array($config) ? ($config['options'] ?? null) : null;

            if (is_array($optionsConfig)) {
                $baseOptions = $optionsConfig;
            }
        }

        $defaults = FormDefaults::builderConfig()['options'];

        $resolvedOptions = array_replace_recursive($defaults, $baseOptions, $options);
        $resolvedOptions['email_recipient'] = $recipient;
        $resolvedOptions['send_email'] = true;

        $formTitle = is_array($existing) && isset($existing['title'])
            ? (string) $existing['title']
            : Text::_('COM_NXPEASYFORMS_UNTITLED_FORM');

        $formIdentifier = 0;

        if (is_array($existing) && is_numeric($existing['id'] ?? null)) {
            $formIdentifier = (int) $existing['id'];
        }

        $result = call_user_func([
            $this->emailService,
            'dispatchSubmission',
        ],
            [
                'id' => $formIdentifier,
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

        $status = !empty($result['sent']) ? 200 : 500;

        return new AjaxResult($result, $status);
    }
}
