<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Ajax\Support;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Language\Text;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Helper service responsible for enforcing component permissions in AJAX flows.
 */
final class PermissionGuard
{
    /**
     * @var CMSApplicationInterface
     */
    private CMSApplicationInterface $application;

    /**
     * Instantiate the guard with the active application instance.
     *
     * @param CMSApplicationInterface $application The application used to perform ACL checks.
     */
    public function __construct(CMSApplicationInterface $application)
    {
        $this->application = $application;
    }

    /**
     * Assert that the current user holds a given permission for this component.
     *
     * @param string $action The ACL action string to evaluate (for example `core.edit`).
     *
     * @return void
     *
     * @throws \RuntimeException When the user lacks the requested permission.
     */
    public function assertAuthorised(string $action): void
    {
        $user = $this->application->getIdentity();

        if (!$user->authorise($action, 'com_nxpeasyforms')) {
            throw new \RuntimeException(Text::_('JGLOBAL_AUTH_ACCESS_DENIED'), 403);
        }
    }
}
