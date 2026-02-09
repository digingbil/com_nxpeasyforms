<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */
declare(strict_types=1);

/**
 * @package     Joomla.Administrator
 * @subpackage  com_nxpeasyforms
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \Joomla\Component\Nxpeasyforms\Administrator\View\Forms\HtmlView $this */

$app = Factory::getApplication();

if ($app->isClient('site')) {
    Session::checkToken('get') or die(Text::_('JINVALID_TOKEN'));
}

/** @var \Joomla\CMS\WebAsset\WebAssetManager $wa */
$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('core')
    ->useScript('modal-content-select');

$function  = $app->getInput()->getCmd('function', 'Joomla.fieldModalSelect.select');
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
$onclick   = $this->escape($function);
?>
<div class="container-popup">
    <form action="<?php echo Route::_('index.php?option=com_nxpeasyforms&view=forms&layout=modal&tmpl=component&function=' . $function . '&' . Session::getFormToken() . '=1'); ?>"
        method="post" name="adminForm" id="adminForm">

        <?php if ($this->filterForm) : ?>
            <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>
        <?php endif; ?>

        <?php if (empty($this->items)) : ?>
            <div class="alert alert-info">
                <span class="icon-info-circle" aria-hidden="true"></span>
                <span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
                <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
            </div>
        <?php else : ?>
            <table class="table table-sm">
                <caption class="visually-hidden">
                    <?php echo Text::_('COM_NXPEASYFORMS_SUBMENU_FORMS'); ?>,
                    <span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?> </span>,
                    <span id="filteredBy"><?php echo Text::_('JGLOBAL_FILTERED_BY'); ?></span>
                </caption>
                <thead>
                <tr>
                    <th scope="col" class="title">
                        <?php echo HTMLHelper::_('searchtools.sort', 'JGLOBAL_TITLE', 'a.title', $listDirn, $listOrder); ?>
                    </th>
                    <th scope="col" class="w-10 d-none d-md-table-cell">
                        <?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.active', $listDirn, $listOrder); ?>
                    </th>
                    <th scope="col" class="w-10 d-none d-md-table-cell">
                        <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
                    </th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($this->items as $i => $item) : ?>
                    <?php
                    $statusClass = (int) $item->active === 1 ? 'icon-publish text-success' : 'icon-unpublish text-danger';
                    $displayTitle = $this->escape($item->title ?: Text::_('COM_NXPEASYFORMS_UNTITLED_FORM'));
                    $attribs = 'data-content-select'
                        . ' data-content-type="com_nxpeasyforms.form"'
                        . ' data-function="' . $onclick . '"'
                        . ' data-id="' . (int) $item->id . '"'
                        . ' data-title="' . $displayTitle . '"'
                        . ' data-html="' . $displayTitle . '"';
                    ?>
                    <tr class="row<?php echo $i % 2; ?>">
                        <th scope="row">
                            <a class="select-link" href="javascript:void(0)" <?php echo $attribs; ?>>
                                <?php echo $displayTitle; ?>
                            </a>
                        </th>
                        <td class="small d-none d-md-table-cell">
                            <span class="<?php echo $statusClass; ?>" aria-hidden="true"></span>
                            <span class="visually-hidden">
                                <?php echo (int) $item->active === 1 ? Text::_('JPUBLISHED') : Text::_('JUNPUBLISHED'); ?>
                            </span>
                        </td>
                        <td class="small d-none d-md-table-cell">
                            <?php echo (int) $item->id; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php echo $this->pagination->getListFooter(); ?>
        <?php endif; ?>

        <?php if ($this->filterForm) : ?>
            <?php echo $this->filterForm->renderControlFields(); ?>
        <?php endif; ?>
    </form>
</div>
