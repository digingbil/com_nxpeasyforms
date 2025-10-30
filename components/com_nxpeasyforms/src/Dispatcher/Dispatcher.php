<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Site\Dispatcher;

use Joomla\CMS\Dispatcher\ComponentDispatcher;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * ComponentDispatcher class for com_nxpeasyforms
 *
 * @since  1.0.0
 */
class Dispatcher extends ComponentDispatcher
{
    /**
     * Dispatch a controller task. Redirecting the user if appropriate.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function dispatch()
    {
        parent::dispatch();
    }
}
