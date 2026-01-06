<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Ajax\Handler;

use Joomla\CMS\Language\Text;
use Joomla\Component\Nxpeasyforms\Administrator\Ajax\AjaxRequestContext;
use Joomla\Component\Nxpeasyforms\Administrator\Ajax\AjaxResult;
use Joomla\Component\Nxpeasyforms\Administrator\Ajax\Support\FormModelFactory;
use Joomla\Component\Nxpeasyforms\Administrator\Ajax\Support\FormPayloadMapper;
use Joomla\Component\Nxpeasyforms\Administrator\Ajax\Support\PermissionGuard;
use RuntimeException;
use function call_user_func;
use function is_array;
use function is_string;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Handles administrator AJAX operations related to form CRUD.
 */
final class FormAjaxHandler
{
    /**
     * @var PermissionGuard
     */
    private PermissionGuard $guard;

    /**
     * @var FormModelFactory
     */
    private FormModelFactory $modelFactory;

    /**
     * @var FormPayloadMapper
     */
    private FormPayloadMapper $mapper;

    /**
     * @param PermissionGuard $guard Performs ACL assertions for AJAX operations.
     * @param FormModelFactory $modelFactory Factory creating administrator form models.
     * @param FormPayloadMapper $mapper Maps payloads between client and storage formats.
     */
    public function __construct(PermissionGuard $guard, FormModelFactory $modelFactory, FormPayloadMapper $mapper)
    {
        $this->guard = $guard;
        $this->modelFactory = $modelFactory;
        $this->mapper = $mapper;
    }

    /**
     * Dispatch a forms-related AJAX request.
     *
     * @param AjaxRequestContext $context Contextual information for the current request.
     * @param string $action The action segment under the forms resource (for example `get`, `save`).
     * @param string $method HTTP method used for the request.
     *
     * @return AjaxResult
     */
    public function dispatch(AjaxRequestContext $context, string $action, string $method): AjaxResult
    {
        return match ($action) {
            'get' => $this->fetchForm($context),
            'save' => $method === 'POST'
                ? $this->saveForm($context)
                : throw new RuntimeException(Text::_('JERROR_PAGE_NOT_FOUND'), 404),
            default => throw new RuntimeException(Text::_('JERROR_PAGE_NOT_FOUND'), 404),
        };
    }

    /**
     * Fetch a form and return its transformed representation.
     *
     * @param AjaxRequestContext $context The current AJAX request context.
     *
     * @return AjaxResult
     */
    private function fetchForm(AjaxRequestContext $context): AjaxResult
    {
        call_user_func(['Joomla\\CMS\\Session\\Session', 'checkToken'], 'post');

        $input = $context->getInput();
        $payload = $input->json->getArray();

        if (!is_array($payload) || empty($payload)) {
            $payload = $input->post->getArray();
        }

        $formId = isset($payload['id']) ? (int) $payload['id'] : (int) $input->getInt('id');

        if ($formId <= 0) {
            throw new RuntimeException(Text::_('COM_NXPEASYFORMS_ERROR_FORM_NOT_FOUND'), 404);
        }

        $this->guard->assertAuthorised('core.edit');

        $model = $this->modelFactory->create();
        $item = call_user_func([$model, 'getItem'], $formId);

        return new AjaxResult($this->mapper->transformForm($item));
    }

    /**
     * Persist form data supplied by the administrator.
     *
     * @param AjaxRequestContext $context The current AJAX request context.
     *
     * @return AjaxResult
     */
    private function saveForm(AjaxRequestContext $context): AjaxResult
    {
        call_user_func(['Joomla\\CMS\\Session\\Session', 'checkToken'], 'post');

        $input = $context->getInput();
        $payload = $input->json->getArray();

        if (!is_array($payload)) {
            $payload = [];
        }

        $formId = isset($payload['id']) ? (int) $payload['id'] : 0;

        $this->guard->assertAuthorised($formId > 0 ? 'core.edit' : 'core.create');

        $model = $this->modelFactory->create();
        $data = $this->mapper->mapPayloadToTable($payload, $formId > 0 ? $formId : null);

        if (!call_user_func([$model, 'save'], $data)) {
            $error = call_user_func([$model, 'getError']);

            throw new RuntimeException($error ?: Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 400);
        }

        $savedId = (int) call_user_func([$model, 'getState'], 'form.id');
        $savedId = $savedId > 0 ? $savedId : $formId;

        $item = call_user_func([$model, 'getItem'], $savedId);

        return new AjaxResult($this->mapper->transformForm($item));
    }
}
