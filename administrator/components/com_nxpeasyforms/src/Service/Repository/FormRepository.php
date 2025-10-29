<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Service\Repository;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;
use Joomla\Event\Event;


use function is_array;
use function json_decode;
use function json_encode;

use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_UNICODE;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Repository providing CRUD operations for form definitions with JSON-encoded configuration payload.
 * Handles storing and retrieving form data including fields, settings, and metadata.
 * @since 1.0.0
 */
class FormRepository
{
    private DatabaseDriver $db;

    public function __construct(?DatabaseDriver $db = null)
    {
        $this->db = $db ?? Factory::getContainer()->get(DatabaseDriver::class);
    }

    /**
     * Finds a form by its ID.
     *
     * @return array<string, mixed>|null
     * @since 1.0.0
     */
    public function find(int $id): ?array
    {
        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('id'),
                $this->db->quoteName('title'),
                $this->db->quoteName('alias'),
                $this->db->quoteName('fields'),
                $this->db->quoteName('settings'),
                $this->db->quoteName('active'),
                $this->db->quoteName('created_at'),
                $this->db->quoteName('updated_at'),
            ])
            ->from($this->db->quoteName('#__nxpeasyforms_forms'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER)
            ->setLimit(1);

        $row = $this->db->setQuery($query)->loadAssoc();

        if (!$row) {
            return null;
        }

        $fields = $this->decodeJson($row['fields'] ?? '[]', []);
        $settings = $this->decodeJson($row['settings'] ?? '{}', []);

        return [
            'id' => (int) $row['id'],
            'title' => $row['title'] ?? '',
            'alias' => $row['alias'] ?? '',
            'active' => (int) ($row['active'] ?? 1),
            'date' => $row['created_at'] ?? null,
            'config' => [
                'fields' => is_array($fields) ? $fields : [],
                'options' => is_array($settings) ? $settings : [],
            ],
        ];
    }

    /**
     * Duplicate an existing form and return the new identifier.
     *
     * @throws \RuntimeException When the source form cannot be duplicated.
     * @since 1.0.0
     */
    public function duplicate(int $id): int
    {
        $form = $this->find($id);

        if ($form === null) {
            throw new \RuntimeException(Text::_('COM_NXPEASYFORMS_ERROR_FORM_NOT_FOUND'), 404);
        }

        $title = $form['title'] ?: Text::_('COM_NXPEASYFORMS_UNTITLED_FORM');
        $copyTitle = Text::sprintf('COM_NXPEASYFORMS_DUPLICATE_TITLE', $title);

        $dispatcher = Factory::getApplication()->getDispatcher();

        if ($dispatcher !== null && method_exists($dispatcher, 'dispatch')) {
            $payload = [
                'title' => &$copyTitle,
                'formId' => $id,
                'form' => $form,
            ];

            $event = new Event('onNxpEasyFormsFilterDuplicateTitle', $payload);
            $dispatcher->dispatch($event->getName(), $event);
        }

        $fields = $form['config']['fields'] ?? [];
        $options = $form['config']['options'] ?? [];

        try {
            $fieldsJson = json_encode(
                $fields,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
            );
            $settingsJson = json_encode(
                $options,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
            );
        } catch (\JsonException $exception) {
            throw new \RuntimeException(
                Text::_('COM_NXPEASYFORMS_ERROR_JSON_ENCODE_FORM_ITEM'),
                500,
                $exception
            );
        }

        $object = (object) [
            'id' => 0,
            'title' => $copyTitle,
            'alias' => null,
            'fields' => $fieldsJson,
            'settings' => $settingsJson,
            'active' => (int) ($form['active'] ?? 1),
        ];

        if (!$this->db->insertObject('#__nxpeasyforms_forms', $object)) {
            throw new \RuntimeException(Text::_('COM_NXPEASYFORMS_ERROR_FORM_DUPLICATE_FAILED'), 500);
        }

        return (int) $object->id;
    }

    /**
     * Updates the configuration of a form.
     *
     * @param array<string, mixed> $config
     * @since 1.0.0
     */
    public function updateConfig(int $id, array $config): bool
    {
        $payload = [
            'fields' => $config['fields'] ?? [],
            'options' => $config['options'] ?? [],
        ];

        $fields = json_encode(
            $payload['fields'],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
        );

        $settings = json_encode(
            $payload['options'],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
        );

        $object = (object) [
            'id' => $id,
            'fields' => $fields,
            'settings' => $settings,
        ];

        return $this->db->updateObject('#__nxpeasyforms_forms', $object, 'id');
    }

    /**
     * Decodes a JSON string or returns a default value if the string is empty or null.
     *
     * @return mixed
     * @since 1.0.0
     */
    private function decodeJson(?string $json, $default)
    {
        if ($json === null || $json === '') {
            return $default;
        }

        try {
	        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            return $default;
        }
    }

    /**
     * Retrieves all forms for selection lists.
     *
     * @return array<int, array<string, mixed>>
     * @since 1.0.0
     */
    public function all(): array
    {
        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('id'),
                $this->db->quoteName('title'),
                $this->db->quoteName('alias'),
                $this->db->quoteName('active'),
            ])
            ->from($this->db->quoteName('#__nxpeasyforms_forms'))
            ->order($this->db->quoteName('title') . ' ASC');

        $rows = $this->db->setQuery($query)->loadAssocList() ?: [];

        foreach ($rows as &$row) {
            $row['id'] = (int) ($row['id'] ?? 0);
            $row['title'] = (string) ($row['title'] ?? '');
            $row['alias'] = $row['alias'] ?? '';
            $row['active'] = (int) ($row['active'] ?? 0);
        }

        return $rows;
    }
}
