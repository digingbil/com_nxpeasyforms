<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Api\View\Submission;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\MVC\View\JsonApiView as BaseApiView;

/**
 * JSON API view for submission resource.
 */
class JsonapiView extends BaseApiView
{
    /**
     * The fields to render item in the documents.
     *
     * @var  array
     */
    protected $fieldsToRenderItem = [];

    /**
     * The fields to render items in the documents.
     *
     * @var  array
     */
    protected $fieldsToRenderList = [];
}
