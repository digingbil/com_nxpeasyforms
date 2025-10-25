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
 * Manages persistence and retrieval of form definitions, including
 * JSON field encoding/decoding and validation.
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
 *
 * @since 1.0.0
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

    /**
     * Constructor.
     *
     * @param   DatabaseDriver  $db  The database driver instance.
     *
     * @since 1.0.0
     */
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__nxpeasyforms_forms', 'id', $db);
    }

    /**
     * Bind data to the table object.
     *
     * Converts incoming data, encoding arrays as JSON for JSON fields
     * and 1 boolean/integer fields.
     *
     * @param array<string,mixed>|object $src Source data to bind.
     * @param array<int,string>|string $ignore Fields to ignore during binding.
     *
     * @return bool
     * @since 1.0.0
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
     * Validate and prepare table data before storage.
     *
     * Ensures the title is not empty, normalizes JSON strings for
     * encoded fields, and validates the active flag.
     *
     * @return bool
     *
     * @throws \InvalidArgumentException When required data is missing.
     * @since 1.0.0
     */
    public function check()
    {
        $this->title = trim($this->title);

        if ($this->title === '') {
            throw new \InvalidArgumentException('COM_NXPEASYFORMS_ERROR_FORM_TITLE_REQUIRED');
        }

        $this->fields = $this->normalizeJsonString($this->fields, 'fields', '[]');
        $this->settings = $this->normalizeJsonString($this->settings, 'settings', '{}');

        $this->active = $this->active ? 1 : 0;

        return true;
    }

    /**
     * Store the table to the database.
     *
     * Overridden to ensure UTC timestamps are set for created_at and updated_at
     * fields when the database driver supports them.
     *
     * @param bool $updateNulls Whether to update null values.
     *
     * @return bool
     *
     * @throws \Joomla\Database\Exception\DatabaseExceptionInterface
     * @return bool
     * @since 1.0.0
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
     * Encode an array payload as JSON.
     *
     * @param array  $payload  The array to encode.
     * @param string   $field    The field name for error reporting.
     *
     * @return string JSON encoded payload.
     *
     * @throws \InvalidArgumentException When JSON encoding fails.
     * @since 1.0.0
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

    /**
     * Normalize and validate a JSON string.
     *
     * @param string|null $value The JSON string to normalize.
     * @param string $field The field name for error reporting.
     * @param string $fallback Default value if normalization fails.
     *
     * @return string Normalized JSON string or fallback.
     *
     * @throws \InvalidArgumentException When JSON is invalid.
     * @since 1.0.0
     */
    private function normalizeJsonString(?string $value, string $field, string $fallback): string
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
