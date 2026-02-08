<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  mod_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
declare(strict_types=1);

namespace Joomla\Module\Nxpeasyforms\Site\Dispatcher;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Helper\HelperFactoryAwareInterface;
use Joomla\CMS\Helper\HelperFactoryAwareTrait;
use Joomla\Module\Nxpeasyforms\Site\Helper\NxpeasyformsHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Dispatcher class for mod_nxpeasyforms.
 */
final class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
{
    use HelperFactoryAwareTrait;

    /**
     * Returns the layout data.
     *
     * @return array<string, mixed>
     */
    protected function getLayoutData()
    {
        $data = parent::getLayoutData();
        $data['renderedForm'] = '';
        $data['showEmptyMessage'] = (bool) $data['params']->get('show_empty_message', true);

        $helper = $this->getHelperFactory()->getHelper('NxpeasyformsHelper');

        if ($helper instanceof NxpeasyformsHelper) {
            $data['renderedForm'] = $helper->renderForm($data['params'], $data['app']);
        }

        return $data;
    }
}
