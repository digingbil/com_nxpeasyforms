<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Ajax;

use Joomla\CMS\Application\CMSApplicationInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Value object carrying application and request input for AJAX handlers.
 */
final class AjaxRequestContext
{
    /**
     * @var object
     */
    private $input;

    /**
     * @var CMSApplicationInterface
     */
    private CMSApplicationInterface $application;

    /**
     * Create a new AJAX request context wrapper.
     *
     * @param object $input The request input instance for the current request.
     * @param CMSApplicationInterface $application The active CMS application handling the request.
     */
    public function __construct(object $input, CMSApplicationInterface $application)
    {
        $this->input = $input;
        $this->application = $application;
    }

    /**
     * Retrieve the Joomla input object associated with the current request.
     *
     * @return object The request input containing query, post, and JSON payload data.
     */
    public function getInput()
    {
        return $this->input;
    }

    /**
     * Retrieve the active application instance handling the current request.
     *
     * @return CMSApplicationInterface The CMS application used for authorisation checks and configuration.
     */
    public function getApplication(): CMSApplicationInterface
    {
        return $this->application;
    }
}
