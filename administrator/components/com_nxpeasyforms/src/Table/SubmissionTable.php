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
 * Table class for NXP Easy Forms submissions.
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

    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__nxpeasyforms_submissions', 'id', $db);
    }

    /**
     * @param array<string,mixed>|object $src
     * @param array<int,string>|string $ignore
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
     * {@inheritDoc}
     */
    public function check()
    {
        if ($this->form_id <= 0) {
            throw new \InvalidArgumentException('COM_NXPEASYFORMS_ERROR_SUBMISSION_FORM_ID_REQUIRED');
        }

        if ($this->submission_uuid === '') {
            throw new \InvalidArgumentException('COM_NXPEASYFORMS_ERROR_SUBMISSION_UUID_REQUIRED');
        }

        $this->data = $this->normaliseJsonString($this->data);

        $this->status = $this->status !== '' ? $this->status : 'new';

        return true;
    }

    /**
     * Overridden to set created timestamp when inserting.
     *
     * @param bool $updateNulls Whether to update null values.
     *
     * @throws DatabaseExceptionInterface
     */
    public function store($updateNulls = false)
    {
        if ($this->id === 0 && !$this->created_at) {
            $this->created_at = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        }

        return parent::store($updateNulls);
    }

    /**
     * @param array<mixed> $payload
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

    private function normaliseJsonString(?string $value): string
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
