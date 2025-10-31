<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Ajax\Support;

use Joomla\CMS\Factory;
use Joomla\Component\Nxpeasyforms\Administrator\Model\FormModel as AdminFormModel;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Factory responsible for creating administrator form models in AJAX flows.
 */
final class FormModelFactory
{
    /**
     * Provide an instance of the administrator form model configured for AJAX usage.
     *
     * @return AdminFormModel The administrator form model with request ignored.
     *
     * @throws \Exception When the component boot sequence fails.
     */
    public function create(): AdminFormModel
    {
        $mvcFactory = $this->getMvcFactory();

        /** @var AdminFormModel $model */
        $model = $mvcFactory->createModel('Form', 'Administrator', ['ignore_request' => true]);

        return $model;
    }

    /**
     * Retrieve the MVC factory for the component, ensuring the component is booted.
     *
    * @return \Joomla\CMS\MVC\Factory\MVCFactoryInterface The factory able to instantiate administrator models.
     *
     * @throws \Exception When component bootstrapping fails.
     */
    private function getMvcFactory()
    {
        return Factory::getApplication()
            ->bootComponent('com_nxpeasyforms')
            ->getMVCFactory();
    }
}
