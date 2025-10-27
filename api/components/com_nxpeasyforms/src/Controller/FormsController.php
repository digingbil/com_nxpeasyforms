<?php

declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Api\Controller;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\ApiController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\Component\Nxpeasyforms\Administrator\Model\FormModel as AdminFormModel;
use Joomla\Component\Nxpeasyforms\Administrator\Model\FormsModel as AdminFormsModel;
use Joomla\Registry\Registry;

use function array_map;
use function array_replace_recursive;
use function is_array;
use function is_string;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * API controller exposing CRUD endpoints for form definitions.
 */
final class FormsController extends ApiController
{
    protected $contentType = 'forms';

    protected $default_view = 'forms';

    public function displayList($cachable = false, $urlparams = [])
    {
        $this->assertAuthorised('core.manage');

        $model = $this->getFormsModel();

        $limit = $this->input->getInt('limit', 20);
        $start = $this->input->getInt('start', 0);
        $search = $this->input->getString('search', '');
        $status = $this->input->getCmd('status', 'all');

        $model->setState('list.limit', $limit);
        $model->setState('list.start', $start);

        if ($search !== '') {
            $model->setState('filter.search', $search);
        }

        if ($status !== '' && $status !== 'all') {
            $model->setState('filter.status', $status);
        }

        $items = $model->getItems() ?? [];
        $pagination = $model->getPagination();

        $data = [
            'data' => array_map([$this, 'transformListItem'], $items),
            'meta' => [
                'pagination' => [
                    'total' => (int) $pagination->total,
                    'limit' => (int) $pagination->limit,
                    'offset' => (int) $pagination->limitstart,
                ],
            ],
        ];

        return new JsonResponse($data);
    }

    public function displayItem($id = null)
    {
        $this->assertAuthorised('core.view');

        $formId = $id ?? $this->input->getInt('id');

        if ($formId <= 0) {
            throw new \RuntimeException(Text::_('COM_NXPEASYFORMS_ERROR_FORM_NOT_FOUND'), 404);
        }

        $item = $this->getFormModel()->getItem($formId);

        if ($item === null) {
            throw new \RuntimeException(Text::_('COM_NXPEASYFORMS_ERROR_FORM_NOT_FOUND'), 404);
        }

        return new JsonResponse($this->transformForm($item));
    }

    public function create()
    {
        $this->assertAuthorised('core.create');

        $payload = $this->getPayload();

        $model = $this->getFormModel();
        $data = $this->mapPayloadToTable($payload);

        if (!$model->save($data)) {
            throw new \RuntimeException($model->getError() ?: Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 400);
        }

        $id = (int) $model->getState('form.id');
        $item = $model->getItem($id);

        return new JsonResponse($this->transformForm($item), 201);
    }

    public function update($id = null)
    {
        $this->assertAuthorised('core.edit');

        $formId = $id ?? $this->input->getInt('id');

        if ($formId <= 0) {
            throw new \RuntimeException(Text::_('COM_NXPEASYFORMS_ERROR_FORM_NOT_FOUND'), 404);
        }

        $payload = $this->getPayload();

        $model = $this->getFormModel();
        $data = $this->mapPayloadToTable($payload, $formId);

        if (!$model->save($data)) {
            throw new \RuntimeException($model->getError() ?: Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 400);
        }

        $item = $model->getItem($formId);

        return new JsonResponse($this->transformForm($item));
    }

    public function delete($id = null)
    {
        $this->assertAuthorised('core.delete');

        $formId = $id ?? $this->input->getInt('id');

        if ($formId <= 0) {
            throw new \RuntimeException(Text::_('COM_NXPEASYFORMS_ERROR_FORM_NOT_FOUND'), 404);
        }

        $model = $this->getFormModel();

        if (!$model->delete([$formId])) {
            throw new \RuntimeException($model->getError() ?: Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 400);
        }

        return new JsonResponse(['success' => true]);
    }

    public function duplicate($id = null)
    {
        $this->assertAuthorised('core.create');

        $formId = $id ?? $this->input->getInt('id');

        if ($formId <= 0) {
            throw new \RuntimeException(Text::_('COM_NXPEASYFORMS_ERROR_FORM_NOT_FOUND'), 404);
        }

        $model = $this->getFormModel();
        $item = $model->getItem($formId);

        if ($item === null) {
            throw new \RuntimeException(Text::_('COM_NXPEASYFORMS_ERROR_FORM_NOT_FOUND'), 404);
        }

        $title = is_string($item->title ?? null) ? $item->title : Text::_('COM_NXPEASYFORMS_UNTITLED_FORM');
        $copyTitle = Text::sprintf('COM_NXPEASYFORMS_DUPLICATE_TITLE', $title);

        $data = [
            'title' => $copyTitle,
            'fields' => $item->fields ?? [],
            'settings' => $item->settings ?? [],
            'active' => (int) ($item->active ?? 1),
        ];

        if (!$model->save($data)) {
            throw new \RuntimeException($model->getError() ?: Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 400);
        }

        $newId = (int) $model->getState('form.id');
        $duplicate = $model->getItem($newId);

        return new JsonResponse($this->transformForm($duplicate), 201);
    }

    private function assertAuthorised(string $action): void
    {
        $user = $this->app->getIdentity();

        if (!$user->authorise($action, 'com_nxpeasyforms')) {
            throw new \RuntimeException(Text::_('JGLOBAL_AUTH_ACCESS_DENIED'), 403);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getPayload(): array
    {
        $payload = $this->input->json->getArray();

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function mapPayloadToTable(array $payload, ?int $id = null): array
    {
        $config = is_array($payload['config'] ?? null) ? $payload['config'] : [];
        $fields = is_array($config['fields'] ?? null) ? $config['fields'] : [];
        $options = is_array($config['options'] ?? null) ? $config['options'] : [];

        $data = [
            'title' => is_string($payload['title'] ?? null) ? trim($payload['title']) : '',
            'fields' => $fields,
            'settings' => $options,
        ];

        if (isset($payload['active'])) {
            $data['active'] = (int) (!empty($payload['active']));
        }

        if ($id !== null) {
            $data['id'] = $id;
        }

        return $data;
    }

    /**
     * @param array<string, mixed>|object $item
     *
     * @return array<string, mixed>
     */
    private function transformListItem($item): array
    {
        $registry = new Registry($item);

        return [
            'id' => (int) $registry->get('id'),
            'title' => (string) $registry->get('title', Text::_('COM_NXPEASYFORMS_UNTITLED_FORM')),
            'active' => (int) $registry->get('active', 1),
            'created_at' => $registry->get('created_at'),
            'updated_at' => $registry->get('updated_at'),
        ];
    }

    /**
     * @param object|null $item
     *
     * @return array<string, mixed>
     */
    private function transformForm(?object $item): array
    {
        if ($item === null) {
            throw new \RuntimeException(Text::_('COM_NXPEASYFORMS_ERROR_FORM_NOT_FOUND'), 404);
        }

        $fields = is_array($item->fields ?? null) ? $item->fields : [];
        $settings = is_array($item->settings ?? null) ? $item->settings : [];

        return [
            'id' => (int) ($item->id ?? 0),
            'title' => (string) ($item->title ?? Text::_('COM_NXPEASYFORMS_UNTITLED_FORM')),
            'active' => (int) ($item->active ?? 1),
            'config' => [
                'fields' => $fields,
                'options' => $settings,
            ],
            'created_at' => $item->created_at ?? null,
            'updated_at' => $item->updated_at ?? null,
        ];
    }

    private function getFormModel(): AdminFormModel
    {
        $factory = Factory::getApplication()
            ->bootComponent('com_nxpeasyforms')
            ->getMVCFactory();

        /** @var AdminFormModel $model */
        $model = $factory->createModel('Form', 'Administrator', ['ignore_request' => true]);

        return $model;
    }

    private function getFormsModel(): AdminFormsModel
    {
        $factory = Factory::getApplication()
            ->bootComponent('com_nxpeasyforms')
            ->getMVCFactory();

        /** @var AdminFormsModel $model */
        $model = $factory->createModel('Forms', 'Administrator', ['ignore_request' => true]);

        return $model;
    }
}
