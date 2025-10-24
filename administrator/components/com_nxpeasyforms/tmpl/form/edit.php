<?php

declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

HTMLHelper::_('behavior.formvalidator');
?>
<form action="<?php echo htmlspecialchars($this->getAction(), ENT_QUOTES, 'UTF-8'); ?>" method="post" name="adminForm" id="adminForm" class="form-validate">
    <div class="card">
        <div class="card-body">
            <div id="nxp-easy-forms-builder-root"
                data-form='<?php echo htmlspecialchars($this->getBuilderPayload(), ENT_QUOTES, 'UTF-8'); ?>'>
                <p class="text-muted">
                    <?php echo Text::_('COM_NXPEASYFORMS_FORM_BUILDER_LOADING'); ?>
                </p>
            </div>
        </div>
    </div>

    <input type="hidden" name="task" value="">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
