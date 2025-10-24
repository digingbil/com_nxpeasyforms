<?php

declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Service\Repository;

use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;

use function array_map;
use function is_array;
use function json_decode;
use function json_encode;

use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_UNICODE;

/**
 * Data access for form submissions using Joomla database APIs.
 */
class SubmissionRepository
{
    private DatabaseDriver $db;

    public function __construct(?DatabaseDriver $db = null)
    {
        $this->db = $db ?? Factory::getContainer()->get(DatabaseDriver::class);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $meta
     */
    public function create(int $formId, string $uuid, array $payload, array $meta = []): int
    {
        $dataJson = json_encode(
            $payload,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
        );

        $row = (object) [
            'form_id' => $formId,
            'submission_uuid' => $uuid,
            'data' => $dataJson,
            'status' => $meta['status'] ?? 'new',
            'ip_address' => $meta['ip_address'] ?? null,
            'user_agent' => $meta['user_agent'] ?? null,
            'created_at' => $this->now(),
        ];

        $this->db->insertObject('#__nxpeasyforms_submissions', $row);

        return (int) $this->db->insertid();
    }

    public function countRecent(int $formId, string $ipAddress, int $seconds): int
    {
        $threshold = $this->now($seconds * -1);

        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__nxpeasyforms_submissions'))
            ->where($this->db->quoteName('form_id') . ' = :formId')
            ->where($this->db->quoteName('ip_address') . ' = :ip')
            ->where($this->db->quoteName('created_at') . ' >= :threshold')
            ->bind(':formId', $formId, ParameterType::INTEGER)
            ->bind(':ip', $ipAddress)
            ->bind(':threshold', $threshold);

        return (int) $this->db->setQuery($query)->loadResult();
    }

    public function countForForm(int $formId): int
    {
        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__nxpeasyforms_submissions'))
            ->where($this->db->quoteName('form_id') . ' = :formId')
            ->bind(':formId', $formId, ParameterType::INTEGER);

        return (int) $this->db->setQuery($query)->loadResult();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allForForm(int $formId): array
    {
        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('id'),
                $this->db->quoteName('submission_uuid'),
                $this->db->quoteName('data'),
                $this->db->quoteName('status'),
                $this->db->quoteName('ip_address'),
                $this->db->quoteName('user_agent'),
                $this->db->quoteName('created_at'),
            ])
            ->from($this->db->quoteName('#__nxpeasyforms_submissions'))
            ->where($this->db->quoteName('form_id') . ' = :formId')
            ->order($this->db->quoteName('created_at') . ' DESC')
            ->bind(':formId', $formId, ParameterType::INTEGER);

        $rows = $this->db->setQuery($query)->loadAssocList();

        if (empty($rows)) {
            return [];
        }

        return array_map(static function (array $row): array {
            $payload = [];

            try {
                $decoded = json_decode($row['data'] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            } catch (\JsonException $exception) {
                $payload = [];
            }

            return [
                'id' => (int) $row['id'],
                'uuid' => (string) $row['submission_uuid'],
                'status' => (string) ($row['status'] ?? 'new'),
                'ip_address' => $row['ip_address'] ?? null,
                'user_agent' => $row['user_agent'] ?? null,
                'created_at' => $row['created_at'] ?? null,
                'data' => $payload,
            ];
        }, $rows);
    }

    public function deleteByFormId(int $formId): int
    {
        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__nxpeasyforms_submissions'))
            ->where($this->db->quoteName('form_id') . ' = :formId')
            ->bind(':formId', $formId, ParameterType::INTEGER);

        $this->db->setQuery($query)->execute();

        return (int) $this->db->getAffectedRows();
    }

    private function now(int $offsetSeconds = 0): string
    {
        $date = new Date('now', 'UTC');

        if ($offsetSeconds !== 0) {
            $modifier = ($offsetSeconds >= 0 ? '+' : '-') . abs($offsetSeconds) . ' seconds';
            $date = $date->modify($modifier);
        }

        return $date->toSql();
    }
}
