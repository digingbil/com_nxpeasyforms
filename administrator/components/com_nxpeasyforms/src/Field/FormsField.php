<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Field;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Repository\FormRepository;
use Joomla\Database\DatabaseDriver;


// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Form field listing available forms for selection (menu items).
 */
final class FormsField extends ListField
{
    /**
     * {@inheritDoc}
     */
    protected $type = 'Forms';

    /**
     * {@inheritDoc}
     */
    protected function getOptions(): array
    {
        $options = parent::getOptions();

        $container = Factory::getContainer();

        try {
            if (method_exists($container, 'has') && $container->has(FormRepository::class)) {
                $repository = $container->get(FormRepository::class);
            } else {
                /** @var DatabaseDriver $db */
                $db = $container->get(DatabaseDriver::class);
                $repository = new FormRepository($db);
            }

            $forms = $repository->all();
        } catch (\Throwable $exception) {
            return $options;
        }

        foreach ($forms as $form) {
            $title = $form['title'] ?: Text::_('COM_NXPEASYFORMS_UNTITLED_FORM');
            $alias = (string) ($form['alias'] ?? '');

            if ($alias !== '') {
                $title = sprintf('%s (%s)', $title, $alias);
            }

            if ((int) ($form['active'] ?? 0) !== 1) {
                $title = sprintf(
                    '%s %s',
                    $title,
                    Text::_('COM_NXPEASYFORMS_FORM_OPTION_INACTIVE_SUFFIX')
                );
            }

            $options[] = HTMLHelper::_('select.option', (int) $form['id'], $title);
        }

        return $options;
    }
}
