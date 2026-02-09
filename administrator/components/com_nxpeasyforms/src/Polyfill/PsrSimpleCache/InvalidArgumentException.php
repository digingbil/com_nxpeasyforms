<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 *
 * PSR-16 SimpleCache InvalidArgumentException polyfill.
 * This interface is only loaded if psr/simple-cache package is not installed.
 */

namespace Psr\SimpleCache;

/**
 * Minimal PSR-16 InvalidArgumentException definition.
 *
 * @since 1.0.0
 */
interface InvalidArgumentException extends \Throwable
{
}
