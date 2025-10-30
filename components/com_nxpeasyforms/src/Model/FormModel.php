<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Site\Model;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ItemModel;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Repository\FormRepository;
use Joomla\Database\DatabaseDriver;


use function is_array;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Site model that exposes a single form definition.
 */
final class FormModel extends ItemModel
{
    private FormRepository $forms;

    public function __construct($config = [], ?MVCFactoryInterface $factory = null, ?FormRepository $forms = null)
    {
        parent::__construct($config, $factory);

        if ($forms !== null) {
            $this->forms = $forms;
        } else {
            $container = Factory::getContainer();

            $resolved = null;

            // Try container->get() if available; on failure, fallback to manual construction
            try {
                if (method_exists($container, 'has') && $container->has(FormRepository::class)) {
                    $resolved = $container->get(FormRepository::class);
                }
            } catch (\Throwable $e) {
                $resolved = null;
            }

            if ($resolved instanceof FormRepository) {
                $this->forms = $resolved;
            } else {
                /** @var DatabaseDriver $db */
                $db = $container->get(DatabaseDriver::class);
                $this->forms = new FormRepository($db);
            }
        }
    }

    /**
     * Loads the active form item.
     *
     * @param int|null $pk Explicit identifier override.
     *
     * @return array<string, mixed>
     */
    public function getItem($pk = null)
    {
        $pk = $pk ?? (int) $this->getState('form.id');

        if ($pk <= 0) {
            throw new \RuntimeException(Text::_('COM_NXPEASYFORMS_ERROR_FORM_NOT_FOUND'), 404);
        }

        $item = $this->forms->find($pk);

        if (!is_array($item) || empty($item)) {
            throw new \RuntimeException(Text::_('COM_NXPEASYFORMS_ERROR_FORM_NOT_FOUND'), 404);
        }

        if ((int) ($item['active'] ?? 1) !== 1) {
            throw new \RuntimeException(Text::_('COM_NXPEASYFORMS_ERROR_FORM_INACTIVE'), 404);
        }

        return $item;
    }

    /**
     * {@inheritDoc}
     */
    protected function populateState()
    {
        parent::populateState();

        $app = Factory::getApplication();
        $input = $app->input;
        $id = $input->getInt('id');

        if ($id <= 0) {
            $menuParams = $app->getParams();
            $id = (int) $menuParams->get('id', 0);
        }

        $this->setState('form.id', $id);
    }
}
