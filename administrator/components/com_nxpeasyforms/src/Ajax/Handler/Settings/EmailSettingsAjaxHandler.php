<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Ajax\Handler\Settings;

use Joomla\CMS\Language\Text;
use Joomla\Component\Nxpeasyforms\Administrator\Ajax\AjaxRequestContext;
use Joomla\Component\Nxpeasyforms\Administrator\Ajax\AjaxResult;
use Joomla\Component\Nxpeasyforms\Administrator\Ajax\Support\EmailSettingsRepository;
use Joomla\Component\Nxpeasyforms\Administrator\Ajax\Support\PermissionGuard;
use Joomla\Component\Nxpeasyforms\Administrator\Helper\FormDefaults;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Email\EmailService;
use RuntimeException;
use function call_user_func;
use function is_array;
use function is_string;
use function trim;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Handles component email settings related AJAX actions.
 */
final class EmailSettingsAjaxHandler
{
    /**
     * @var PermissionGuard
     */
    private PermissionGuard $guard;

    /**
     * @var EmailSettingsRepository
     */
    private EmailSettingsRepository $repository;

    /**
     * @var EmailService
     */
    private EmailService $emailService;

    /**
     * @param PermissionGuard $guard Performs ACL assertions.
     * @param EmailSettingsRepository $repository Repository encapsulating component settings persistence.
     * @param EmailService $emailService Service used to dispatch test submissions.
     */
    public function __construct(PermissionGuard $guard, EmailSettingsRepository $repository, EmailService $emailService)
    {
        $this->guard = $guard;
        $this->repository = $repository;
        $this->emailService = $emailService;
    }

    /**
     * Retrieve current component email settings for the administrator UI.
     *
     * @param AjaxRequestContext $context Current AJAX request context.
     *
     * @return AjaxResult
     */
    public function getSettings(AjaxRequestContext $context): AjaxResult
    {
        $this->guard->assertAuthorised('core.manage');

        $settings = $this->repository->fetchSettings(false);

        return new AjaxResult([
            'success' => true,
            'settings' => $settings,
        ]);
    }

    /**
     * Persist component email settings supplied by the administrator.
     *
     * @param AjaxRequestContext $context Current AJAX request context.
     *
     * @return AjaxResult
     */
    public function saveSettings(AjaxRequestContext $context): AjaxResult
    {
        call_user_func(['Joomla\\CMS\\Session\\Session', 'checkToken'], 'post');
        $this->guard->assertAuthorised('core.admin');

        $payload = $context->getInput()->json->getArray();

        if (!is_array($payload)) {
            throw new RuntimeException(Text::_('JERROR_INPUT_DATA_INVALID'), 400);
        }

        $this->repository->saveSettings($payload);

        return new AjaxResult(['success' => true]);
    }

    /**
     * Provide a lightweight diagnostics report for email subsystems.
     *
     * @param AjaxRequestContext $context Current AJAX request context.
     *
     * @return AjaxResult
     */
    public function diagnostics(AjaxRequestContext $context): AjaxResult
    {
        $this->guard->assertAuthorised('core.manage');

        return new AjaxResult([
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
     * Send a component-level test email using saved configuration values.
     *
     * @param AjaxRequestContext $context Current AJAX request context.
     *
     * @return AjaxResult
     */
    public function sendTestEmail(AjaxRequestContext $context): AjaxResult
    {
        call_user_func(['Joomla\\CMS\\Session\\Session', 'checkToken'], 'post');
        $this->guard->assertAuthorised('core.manage');

        $payload = $context->getInput()->json->getArray();
        $recipient = is_string($payload['recipient'] ?? null)
            ? trim($payload['recipient'])
            : '';

        if ($recipient === '') {
            throw new RuntimeException(Text::_('COM_NXPEASYFORMS_EMAIL_NO_RECIPIENT'), 400);
        }

        $defaults = FormDefaults::builderConfig()['options'];
        $options = $this->repository->buildSettingsTestOptions($recipient, $defaults);

        $result = call_user_func([
            $this->emailService,
            'dispatchSubmission',
        ],
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

        $status = !empty($result['sent']) ? 200 : 500;

        return new AjaxResult($result, $status);
    }
}
