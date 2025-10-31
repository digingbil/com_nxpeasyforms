<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Ajax;

use Joomla\CMS\Language\Text;
use Joomla\Component\Nxpeasyforms\Administrator\Ajax\Handler\EmailAjaxHandler;
use Joomla\Component\Nxpeasyforms\Administrator\Ajax\Handler\FormAjaxHandler;
use Joomla\Component\Nxpeasyforms\Administrator\Ajax\Handler\Integrations\MailchimpAjaxHandler;
use Joomla\Component\Nxpeasyforms\Administrator\Ajax\Handler\Settings\EmailSettingsAjaxHandler;
use Joomla\Component\Nxpeasyforms\Administrator\Ajax\Handler\Settings\JoomlaSettingsAjaxHandler;
use RuntimeException;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Routes administrator AJAX requests to handler classes.
 */
final class AjaxRouter
{
    /**
     * @var FormAjaxHandler
     */
    private FormAjaxHandler $forms;

    /**
     * @var EmailAjaxHandler
     */
    private EmailAjaxHandler $emails;

    /**
     * @var EmailSettingsAjaxHandler
     */
    private EmailSettingsAjaxHandler $emailSettings;

    /**
     * @var JoomlaSettingsAjaxHandler
     */
    private JoomlaSettingsAjaxHandler $joomlaSettings;

    /**
     * @var MailchimpAjaxHandler
     */
    private MailchimpAjaxHandler $mailchimp;

    /**
     * @param FormAjaxHandler $forms Handler for forms resource.
     * @param EmailAjaxHandler $emails Handler for emails resource.
     * @param EmailSettingsAjaxHandler $emailSettings Handler for email settings.
     * @param JoomlaSettingsAjaxHandler $joomlaSettings Handler for Joomla settings.
     * @param MailchimpAjaxHandler $mailchimp Handler for Mailchimp integration requests.
     */
    public function __construct(
        FormAjaxHandler $forms,
        EmailAjaxHandler $emails,
        EmailSettingsAjaxHandler $emailSettings,
        JoomlaSettingsAjaxHandler $joomlaSettings,
        MailchimpAjaxHandler $mailchimp
    ) {
        $this->forms = $forms;
        $this->emails = $emails;
        $this->emailSettings = $emailSettings;
        $this->joomlaSettings = $joomlaSettings;
        $this->mailchimp = $mailchimp;
    }

    /**
     * Dispatch the request to the appropriate handler based on resource and action.
     *
     * @param AjaxRequestContext $context Current request context.
     * @param array<int,string> $segments Parsed path segments.
     * @param string $method HTTP method used for the request.
     *
     * @return AjaxResult
     */
    public function dispatch(AjaxRequestContext $context, array $segments, string $method): AjaxResult
    {
        $resource = $segments[0] ?? '';

        return match ($resource) {
            'forms' => $this->forms->dispatch($context, $segments[1] ?? '', $method),
            'emails' => $this->emails->dispatch($context, $segments[1] ?? '', $method),
            'settings' => $this->dispatchSettings($context, $segments, $method),
            'integrations' => $this->dispatchIntegrations($context, $segments, $method),
            default => throw new RuntimeException(Text::_('JERROR_PAGE_NOT_FOUND'), 404),
        };
    }

    /**
     * Dispatch settings-related requests to the appropriate subsection handler.
     *
     * @param AjaxRequestContext $context Current request context.
     * @param array<int,string> $segments Parsed path segments.
     * @param string $method HTTP method used for the request.
     *
     * @return AjaxResult
     */
    private function dispatchSettings(AjaxRequestContext $context, array $segments, string $method): AjaxResult
    {
        $section = $segments[1] ?? '';

        return match ($section) {
            'email' => $this->dispatchEmailSettings($context, $segments, $method),
            'joomla' => $this->joomlaSettings->dispatch($context, $segments[2] ?? '', $method),
            default => throw new RuntimeException(Text::_('JERROR_PAGE_NOT_FOUND'), 404),
        };
    }

    /**
     * Route email settings actions to the appropriate handler method.
     *
     * @param AjaxRequestContext $context Current request context.
     * @param array<int,string> $segments Path segments under settings/email.
     * @param string $method HTTP method used for the request.
     *
     * @return AjaxResult
     */
    private function dispatchEmailSettings(AjaxRequestContext $context, array $segments, string $method): AjaxResult
    {
        $action = $segments[2] ?? '';

        return match ($action) {
            '' => $this->emailSettings->getSettings($context),
            'save' => $method === 'POST'
                ? $this->emailSettings->saveSettings($context)
                : throw new RuntimeException(Text::_('JERROR_PAGE_NOT_FOUND'), 404),
            'test' => $method === 'POST'
                ? $this->emailSettings->sendTestEmail($context)
                : throw new RuntimeException(Text::_('JERROR_PAGE_NOT_FOUND'), 404),
            'diagnostics' => $this->emailSettings->diagnostics($context),
            default => throw new RuntimeException(Text::_('JERROR_PAGE_NOT_FOUND'), 404),
        };
    }

    /**
     * Dispatch integration-related requests.
     *
     * @param AjaxRequestContext $context Current request context.
     * @param array<int,string> $segments Parsed path segments.
     * @param string $method HTTP method used for the request.
     *
     * @return AjaxResult
     */
    private function dispatchIntegrations(AjaxRequestContext $context, array $segments, string $method): AjaxResult
    {
        $integration = $segments[1] ?? '';

        return match ($integration) {
            'mailchimp' => $this->mailchimp->dispatch($context, $segments[2] ?? '', $method),
            default => throw new RuntimeException(Text::_('JERROR_PAGE_NOT_FOUND'), 404),
        };
    }
}
