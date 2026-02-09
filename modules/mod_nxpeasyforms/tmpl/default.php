<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  mod_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */
declare(strict_types=1);

use Joomla\CMS\Language\Text;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

if ($renderedForm !== '') {
    echo $renderedForm;

    return;
}

$canManageModules = $app->getIdentity()->authorise('core.manage', 'com_modules');

if ($showEmptyMessage && $canManageModules) {
    echo '<div class="alert alert-info">'
        . htmlspecialchars(Text::_('MOD_NXPEASYFORMS_EMPTY_MESSAGE'), ENT_QUOTES, 'UTF-8')
        . '</div>';
}
