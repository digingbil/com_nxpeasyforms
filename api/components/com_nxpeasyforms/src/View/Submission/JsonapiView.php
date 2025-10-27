<?php

declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Api\View\Submission;

use Joomla\CMS\MVC\View\JsonApiView as BaseApiView;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

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
