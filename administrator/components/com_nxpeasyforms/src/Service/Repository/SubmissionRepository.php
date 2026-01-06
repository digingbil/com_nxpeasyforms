<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Service\Repository;

use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;
use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function implode;
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
 * Repository class handling data access and management for form submissions using Joomla database APIs.
 * Provides CRUD operations and querying capabilities for form submission records.
 * @since 1.0.0
 */
class SubmissionRepository
{
    private DatabaseDriver $db;

    public function __construct(?DatabaseDriver $db = null)
    {
        $this->db = $db ?? Factory::getContainer()->get(DatabaseDriver::class);
    }

    /**
     * Creates a new submission record in the database.
     *
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

	/**
	 * Counts the number of recent submissions for a form within a specified time range.
	 * @param int $formId
	 * @param string $ipAddress
	 * @param int $seconds
	 * @return int
	 * @since 1.0.0
	 */
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

	/**
	 * Counts the number of submissions for a form.
	 * @param int $formId
	 * @return int
	 * @since 1.0.0
	 */
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
     * Get all submissions for a form.
     *
     * @return array<int, array<string, mixed>>
     * @since 1.0.0
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

    /**
     * Delete submissions by their identifiers.
     *
     * @param   array<int>  $ids  Submission identifiers to remove.
     *
     * @return void
     *
     * @throws \Throwable When the delete query fails to execute.
     * @since 1.0.0
     */
    public function deleteByIds(array $ids): void
    {
        $ids = array_values(array_unique(
            array_filter(
                array_map('intval', $ids),
                static fn (int $id): bool => $id > 0
            )
        ));

        if ($ids === []) {
            return;
        }

        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__nxpeasyforms_submissions'));

        $placeholders = [];

        foreach ($ids as $index => $id) {
            $placeholder = ':id' . $index;
            $placeholders[] = $placeholder;
            $query->bind($placeholder, $id, ParameterType::INTEGER);
        }

        $query->where(
            $this->db->quoteName('id') . ' IN (' . implode(',', $placeholders) . ')'
        );

        $this->db->setQuery($query)->execute();
    }

	/**
	 * Deletes all submissions associated with a specific form ID.
	 *
	 * @param   int  $formId  The ID of the form whose submissions will be deleted.
	 *
	 * @return int The number of rows affected by the delete operation.
	 * @since 1.0.0
	 */
    public function deleteByFormId(int $formId): int
    {
        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__nxpeasyforms_submissions'))
            ->where($this->db->quoteName('form_id') . ' = :formId')
            ->bind(':formId', $formId, ParameterType::INTEGER);

        $this->db->setQuery($query)->execute();

        return (int) $this->db->getAffectedRows();
    }

	/**
	 * Find submissions by their unique identifiers.
	 *
	 * @param   array<int>  $ids  Submission identifiers to load.
	 *
	 * @return array<int, array<string, mixed>>
	 * @since 1.0.0
	 */
    public function findByIds(array $ids): array
    {
        $ids = array_values(array_unique(
            array_filter(
                array_map('intval', $ids),
                static fn (int $id): bool => $id > 0
            )
        ));

        if ($ids === []) {
            return [];
        }

        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('a.id'),
                $this->db->quoteName('a.form_id'),
                $this->db->quoteName('a.submission_uuid'),
                $this->db->quoteName('a.status'),
                $this->db->quoteName('a.ip_address'),
                $this->db->quoteName('a.user_agent'),
                $this->db->quoteName('a.data'),
                $this->db->quoteName('a.created_at'),
                $this->db->quoteName('f.title', 'form_title'),
            ])
            ->from($this->db->quoteName('#__nxpeasyforms_submissions', 'a'))
            ->join(
                'LEFT',
                $this->db->quoteName('#__nxpeasyforms_forms', 'f'),
                $this->db->quoteName('f.id') . ' = ' . $this->db->quoteName('a.form_id')
            )
            ->order($this->db->quoteName('a.created_at') . ' DESC');

        // Use parameterized binding for IN clause
        $placeholders = [];
        foreach ($ids as $index => $id) {
            $paramName = ':id' . $index;
            $placeholders[] = $paramName;
            $query->bind($paramName, $ids[$index], ParameterType::INTEGER);
        }
        $query->where($this->db->quoteName('a.id') . ' IN (' . implode(',', $placeholders) . ')');

        $rows = $this->db->setQuery($query)->loadAssocList() ?: [];

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
                'id' => (int) ($row['id'] ?? 0),
                'form_id' => (int) ($row['form_id'] ?? 0),
                'form_title' => $row['form_title'] ?? null,
                'submission_uuid' => (string) ($row['submission_uuid'] ?? ''),
                'status' => (string) ($row['status'] ?? 'new'),
                'ip_address' => $row['ip_address'] ?? null,
                'user_agent' => $row['user_agent'] ?? null,
                'created_at' => $row['created_at'] ?? null,
                'payload' => $payload,
            ];
        }, $rows);
    }

	/**
	 * Gets the current UTC time as a SQL-formatted string, with an optional offset in seconds.
	 *
	 * @param   int  $offsetSeconds  Number of seconds to offset the current time. Defaults to 0 for current time.
	 *
	 * @return string SQL-formatted date and time string.
	 * @since 1.0.0
	 */
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
