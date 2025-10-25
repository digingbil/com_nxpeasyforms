<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Site\Model;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\ItemModel;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Repository\FormRepository;


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

    public function __construct($config = [], ?FormRepository $forms = null)
    {
        parent::__construct($config);
        $this->forms = $forms ?? Factory::getContainer()->get(FormRepository::class);
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
            return [
                'id' => 0,
                'title' => '',
                'active' => 0,
                'config' => [
                    'fields' => [],
                    'options' => [],
                ],
            ];
        }

        $item = $this->forms->find($pk);

        if (!is_array($item) || empty($item)) {
            throw new \RuntimeException(Text::_('COM_NXPEASYFORMS_ERROR_FORM_NOT_FOUND'), 404);
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

        $this->setState('form.id', $id);
    }
}
