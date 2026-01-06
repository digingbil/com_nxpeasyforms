<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
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
 * Table class for NXP Easy Forms submissions.
 *
 * Manages persistence and retrieval of form submission records, including
 * JSON payload handling and validation.
 *
 * @since 1.0.0
 */
final class SubmissionTable extends Table
{
    public $id = 0;

    public $form_id = 0;

    public $submission_uuid = '';

    /**
     * JSON encoded submission payload.
     */
    public $data = '{}';

    public $status = 'new';

    public $ip_address = null;

    public $user_agent = null;

    public $created_at = null;

    /**
     * Constructor.
     *
     * @param \Joomla\Database\DatabaseDriver $db The database driver instance.
     *
     * @since 1.0.0
     */
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__nxpeasyforms_submissions', 'id', $db);
    }

    /**
     * Bind data to the table object.
     *
     * Converts incoming data, encoding arrays as JSON for the data field
     * and normalizing related fields.
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

        if (array_key_exists('data', $data) && is_array($data['data'])) {
            $data['data'] = $this->encodeJson($data['data']);
        }

        if (array_key_exists('form_id', $data)) {
            $data['form_id'] = (int) $data['form_id'];
        }

        if (array_key_exists('status', $data) && is_string($data['status'])) {
            $data['status'] = strtolower($data['status']);
        }

        return parent::bind($data, $ignore);
    }

    /**
     * Validate and prepare table data before storage.
     *
     * Ensures the form_id and submission_uuid are set, normalizes JSON strings
     * for the data field, and validates the status.
     *
     * @return bool
     *
     * @throws \InvalidArgumentException When required data is missing.
     * @since 1.0.0
     */
    public function check()
    {
        if ($this->form_id <= 0) {
            throw new \InvalidArgumentException('COM_NXPEASYFORMS_ERROR_SUBMISSION_FORM_ID_REQUIRED');
        }

        if ($this->submission_uuid === '') {
            throw new \InvalidArgumentException('COM_NXPEASYFORMS_ERROR_SUBMISSION_UUID_REQUIRED');
        }

        $this->data = $this->normalizeJsonString($this->data);

        $this->status = $this->status !== '' ? $this->status : 'new';

        return true;
    }

    /**
     * Store the table to the database.
     *
     * Overridden to set the created_at timestamp on initial insert.
     *
     * @param bool $updateNulls Whether to update null values.
     *
     * @return bool
     *
     * @throws \Joomla\Database\Exception\DatabaseExceptionInterface
     * @since 1.0.0
     */
    public function store($updateNulls = false)
    {
        if ($this->id === 0 && !$this->created_at) {
            $this->created_at = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        }

        return parent::store($updateNulls);
    }

    /**
     * Encode an array payload as JSON.
     *
     * @param array<mixed> $payload The array to encode.
     *
     * @return string JSON encoded payload.
     *
     * @throws \InvalidArgumentException When JSON encoding fails.
     * @since 1.0.0
     */
    private function encodeJson(array $payload): string
    {
        try {
            return json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
            );
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException(
                'COM_NXPEASYFORMS_ERROR_JSON_ENCODE_SUBMISSION',
                0,
                $exception
            );
        }
    }

    /**
     * Normalize and validate a JSON string.
     *
     * @param string|null $value The JSON string to normalize.
     *
     * @return string Normalized JSON string or fallback object.
     *
     * @throws \InvalidArgumentException When JSON is invalid.
     * @since 1.0.0
     */
    private function normalizeJsonString(?string $value): string
    {
        $value = $value ?? '{}';

        if ($value === '') {
            return '{}';
        }

        try {
            json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException(
                'COM_NXPEASYFORMS_ERROR_JSON_DECODE_SUBMISSION',
                0,
                $exception
            );
        }

        return $value;
    }
}
