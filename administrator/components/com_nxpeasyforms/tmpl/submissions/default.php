<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

HTMLHelper::_('behavior.multiselect');

$listOrder = $this->escape($this->state->get('list.ordering', 'created_at'));
$listDirn = $this->escape($this->state->get('list.direction', 'DESC'));
?>
<form action="<?php echo Route::_('index.php?option=com_nxpeasyforms&view=submissions'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-12 col-lg-6">
            <?php if (!empty($this->filterForm)) : ?>
                <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
            <tr>
                <th scope="col" class="w-1 text-center">
                    <?php echo HTMLHelper::_('grid.checkall'); ?>
                </th>
                <th scope="col">
                    <?php echo HTMLHelper::_('searchtools.sort', Text::_('COM_NXPEASYFORMS_COLUMN_SUBMISSION_UUID'), 'submission_uuid', $listDirn, $listOrder); ?>
                </th>
                <th scope="col">
                    <?php echo Text::_('COM_NXPEASYFORMS_COLUMN_FORM_TITLE'); ?>
                </th>
                <th scope="col" class="w-10 text-center">
                    <?php echo HTMLHelper::_('searchtools.sort', Text::_('COM_NXPEASYFORMS_COLUMN_STATUS'), 'status', $listDirn, $listOrder); ?>
                </th>
                <th scope="col" class="w-15">
                    <?php echo HTMLHelper::_('searchtools.sort', Text::_('COM_NXPEASYFORMS_COLUMN_CREATED'), 'created_at', $listDirn, $listOrder); ?>
                </th>
                <th scope="col" class="w-5 text-end">
                    <?php echo Text::_('JGRID_HEADING_ID'); ?>
                </th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($this->items)) : ?>
                <tr>
                    <td colspan="6" class="text-center">
                        <?php echo Text::_('COM_NXPEASYFORMS_SUBMISSIONS_LIST_EMPTY'); ?>
                    </td>
                </tr>
            <?php else : ?>
                <?php foreach ($this->items as $i => $item) : ?>
                    <tr>
                        <td class="text-center">
                            <?php echo HTMLHelper::_('grid.id', $i, $item->id); ?>
                        </td>
                        <td>
                            <code><?php echo $this->escape($item->submission_uuid); ?></code>
                        </td>
                        <td>
                            <?php echo $this->escape($item->form_title ?? Text::_('COM_NXPEASYFORMS_UNKNOWN_FORM')); ?>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-info">
                                <?php echo $this->escape(strtoupper($item->status)); ?>
                            </span>
                        </td>
                        <td>
                            <?php echo $item->created_at
                                ? HTMLHelper::_('date', $item->created_at, Text::_('DATE_FORMAT_LC3'))
                                : '—'; ?>
                        </td>
                        <td class="text-end">
                            <?php echo (int) $item->id; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php echo $this->pagination->getListFooter(); ?>

    <input type="hidden" name="task" value="">
    <input type="hidden" name="boxchecked" value="0">
    <input type="hidden" name="filter_order" value="<?php echo $listOrder; ?>">
    <input type="hidden" name="filter_order_Dir" value="<?php echo $listDirn; ?>">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
