<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Table;

use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\Exception\DatabaseExceptionInterface;
use Joomla\Utilities\ArrayHelper;


// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Table class for NXP Easy Forms definitions.
 *
 * @psalm-type FormPayload = array{
 *     id?: int,
 *     title?: string,
 *     fields?: array<mixed>|string|null,
 *     settings?: array<mixed>|string|null,
 *     active?: int|bool,
 *     created_at?: string|null,
 *     updated_at?: string|null
 * }
 */
final class FormTable extends Table
{
    public $id = 0;

    public $title = '';

    /**
     * JSON encoded field definitions.
     */
    public $fields = '[]';

    /**
     * JSON encoded settings payload.
     */
    public $settings = '{}';

    public $active = 1;

    public $created_at = null;

    public $updated_at = null;

    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__nxpeasyforms_forms', 'id', $db);
    }

    /**
     * @param array<string,mixed>|object $src
     * @param array<int,string>|string $ignore
     */
    public function bind($src, $ignore = []): bool
    {
        $data = is_array($src) ? $src : ArrayHelper::fromObject($src);

        if (array_key_exists('fields', $data) && is_array($data['fields'])) {
            $data['fields'] = $this->encodeJson($data['fields'], 'fields');
        }

        if (array_key_exists('settings', $data) && is_array($data['settings'])) {
            $data['settings'] = $this->encodeJson($data['settings'], 'settings');
        }

        if (array_key_exists('active', $data)) {
            $data['active'] = (int) ($data['active'] ? 1 : 0);
        }

        return parent::bind($data, $ignore);
    }

    /**
     * {@inheritDoc}
     *
     * @throws \InvalidArgumentException When required data is missing.
     */
    public function check()
    {
        $this->title = trim($this->title);

        if ($this->title === '') {
            throw new \InvalidArgumentException('COM_NXPEASYFORMS_ERROR_FORM_TITLE_REQUIRED');
        }

        $this->fields = $this->normaliseJsonString($this->fields, 'fields', '[]');
        $this->settings = $this->normaliseJsonString($this->settings, 'settings', '{}');

        $this->active = $this->active ? 1 : 0;

        return true;
    }

    /**
     * Overridden to ensure UTC timestamps when database supports them.
     *
     * @param bool $updateNulls Whether to update null values.
     *
     * @throws DatabaseExceptionInterface
     */
    public function store($updateNulls = false)
    {
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        if ($this->id === 0 && !$this->created_at) {
            $this->created_at = $now;
        }

        $this->updated_at = $now;

        return parent::store($updateNulls);
    }

    /**
     * Encodes array payload as JSON.
     *
     * @param array<mixed> $payload
     */
    private function encodeJson(array $payload, string $field): string
    {
        try {
            return json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
            );
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException(
                sprintf('COM_NXPEASYFORMS_ERROR_JSON_ENCODE_%s', strtoupper($field)),
                0,
                $exception
            );
        }
    }

    private function normaliseJsonString(?string $value, string $field, string $fallback): string
    {
        $value = $value ?? $fallback;

        if ($value === '') {
            return $fallback;
        }

        try {
            json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException(
                sprintf('COM_NXPEASYFORMS_ERROR_JSON_DECODE_%s', strtoupper($field)),
                0,
                $exception
            );
        }

        return $value;
    }
}
