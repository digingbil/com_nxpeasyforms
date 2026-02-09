<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Ajax\Handler\Integrations;

use Joomla\CMS\Language\Text;
use Joomla\Component\Nxpeasyforms\Administrator\Ajax\AjaxRequestContext;
use Joomla\Component\Nxpeasyforms\Administrator\Ajax\AjaxResult;
use Joomla\Component\Nxpeasyforms\Administrator\Ajax\Support\MailchimpIntegrationService;
use Joomla\Component\Nxpeasyforms\Administrator\Ajax\Support\PermissionGuard;
use RuntimeException;
use function call_user_func;
use function is_array;
use function trim;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Handles Mailchimp integration AJAX actions.
 */
final class MailchimpAjaxHandler
{
    /**
     * @var PermissionGuard
     */
    private PermissionGuard $guard;

    /**
     * @var MailchimpIntegrationService
     */
    private MailchimpIntegrationService $mailchimp;

    /**
     * @param PermissionGuard $guard Performs ACL assertions.
     * @param MailchimpIntegrationService $mailchimp Provides Mailchimp-specific helpers.
     */
    public function __construct(PermissionGuard $guard, MailchimpIntegrationService $mailchimp)
    {
        $this->guard = $guard;
        $this->mailchimp = $mailchimp;
    }

    /**
     * Dispatch a Mailchimp integration AJAX request.
     *
     * @param AjaxRequestContext $context Current request context.
     * @param string $action Action segment under integrations/mailchimp.
     * @param string $method HTTP method used for the request.
     *
     * @return AjaxResult
     */
    public function dispatch(AjaxRequestContext $context, string $action, string $method): AjaxResult
    {
        return match ($action) {
            'lists' => $method === 'POST'
                ? $this->fetchLists($context)
                : throw new RuntimeException(Text::_('JERROR_PAGE_NOT_FOUND'), 404),
            default => throw new RuntimeException(Text::_('JERROR_PAGE_NOT_FOUND'), 404),
        };
    }

    /**
     * Fetch Mailchimp audiences for the builder integration modal.
     *
     * @param AjaxRequestContext $context Current request context.
     *
     * @return AjaxResult
     */
    private function fetchLists(AjaxRequestContext $context): AjaxResult
    {
        call_user_func(['Joomla\\CMS\\Session\\Session', 'checkToken'], 'post');

        $payload = $context->getInput()->json->getArray();

        if (!is_array($payload)) {
            $payload = [];
        }

        $providedKey = isset($payload['apiKey']) ? trim((string) $payload['apiKey']) : '';
        $formId = isset($payload['formId']) ? (int) $payload['formId'] : 0;

        $this->guard->assertAuthorised($formId > 0 ? 'core.edit' : 'core.create');

        $apiKey = $this->mailchimp->resolveApiKey($providedKey, $formId);

        if ($apiKey === '') {
            throw new RuntimeException(Text::_('COM_NXPEASYFORMS_MAILCHIMP_API_KEY_REQUIRED'), 400);
        }

        try {
            $lists = $this->mailchimp->fetchLists($apiKey);
        } catch (RuntimeException $exception) {
            throw new RuntimeException($exception->getMessage(), $exception->getCode() ?: 502, $exception);
        }

        return new AjaxResult([
            'lists' => $lists,
        ]);
    }
}
