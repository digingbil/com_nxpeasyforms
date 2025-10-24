<?php

declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$form = $this->getFormData();
?>
<div class="nxp-easy-form" data-form-id="<?php echo (int) ($form['id'] ?? 0); ?>">
    <p class="nxp-easy-form__notice">
        <?php echo Text::_('COM_NXPEASYFORMS_FORM_PLACEHOLDER_MESSAGE'); ?>
    </p>
</div>
