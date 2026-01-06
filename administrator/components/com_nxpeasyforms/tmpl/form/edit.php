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

HTMLHelper::_('behavior.formvalidator');
?>
<form action="<?php echo htmlspecialchars($this->getAction(), ENT_QUOTES, 'UTF-8'); ?>" method="post" name="adminForm" id="adminForm" class="form-validate">
    <div class="card">
        <div class="card-body">
            <div id="nxp-easy-forms-builder" class="nxp-easy-forms-builder">
                <p class="text-muted">
                    <?php echo Text::_('COM_NXPEASYFORMS_FORM_BUILDER_LOADING'); ?>
                </p>
            </div>
        </div>
    </div>

    <input type="hidden" name="task" value="">
    <?php echo HTMLHelper::_('form.token'); ?>
    <div class="d-none" aria-hidden="true">
        <?php echo $this->form->getInput('id'); ?>
        <?php echo $this->form->getInput('title'); ?>
        <?php echo $this->form->getInput('alias'); ?>
        <?php echo $this->form->getInput('active'); ?>
        <?php echo $this->form->getInput('fields'); ?>
        <?php echo $this->form->getInput('settings'); ?>
    </div>
</form>
