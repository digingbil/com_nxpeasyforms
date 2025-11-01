<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Service\Export;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Repository\SubmissionRepository;
use JsonException;
use RuntimeException;
use function fclose;
use function fopen;
use function fputcsv;
use function implode;
use function in_array;
use function gmdate;
use function json_encode;
use function rewind;
use function stream_get_contents;
use function strtolower;
use function str_replace;
use function trim;
use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_UNICODE;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Handles serialization of submission data into downloadable export formats.
 *
 * @since 1.0.0
 */
final class SubmissionExporter
{
    private SubmissionRepository $repository;

    public function __construct(?SubmissionRepository $repository = null)
    {
        if ($repository instanceof SubmissionRepository) {
            $this->repository = $repository;

            return;
        }

        $container = Factory::getContainer();

        if (method_exists($container, 'has') && $container->has(SubmissionRepository::class)) {
            $this->repository = $container->get(SubmissionRepository::class);

            return;
        }

        $this->repository = new SubmissionRepository();
    }

    /**
     * Create an export payload for the provided submission identifiers.
     *
     * @param   array<int>  $ids     Identifiers to export.
     * @param   string      $format  Target format (csv or json).
     */
    public function export(array $ids, string $format = 'csv'): SubmissionExportResult
    {
        $format = strtolower($format);

        if (!in_array($format, ['csv', 'json'], true)) {
            throw new RuntimeException(Text::sprintf('COM_NXPEASYFORMS_EXPORT_UNSUPPORTED_FORMAT', $format));
        }

        $records = $this->repository->findByIds($ids);

        if ($records === []) {
            throw new RuntimeException(Text::_('COM_NXPEASYFORMS_EXPORT_NO_RESULTS'));
        }

    $timestamp = gmdate('Ymd_His');

        if ($format === 'json') {
            $body = $this->serializeJson($records);

            return new SubmissionExportResult(
                'submissions_' . $timestamp . '.json',
                'application/json',
                $body
            );
        }

        $body = $this->serializeCsv($records);

        return new SubmissionExportResult(
            'submissions_' . $timestamp . '.csv',
            'text/csv; charset=utf-8',
            $body
        );
    }

    /**
     * Serialize records to JSON.
     *
     * @param   array<int, array<string, mixed>>  $records  Rows to serialize.
     */
    private function serializeJson(array $records): string
    {
        try {
            return json_encode(
                $records,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(Text::_('COM_NXPEASYFORMS_EXPORT_SERIALIZE_FAILED'), 0, $exception);
        }
    }

    /**
     * Serialize records to CSV.
     *
     * @param   array<int, array<string, mixed>>  $records  Rows to serialize.
     */
    private function serializeCsv(array $records): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new RuntimeException(Text::_('COM_NXPEASYFORMS_EXPORT_STREAM_FAILED'));
        }

        $header = [
            'id',
            'form_id',
            'form_title',
            'submission_uuid',
            'status',
            'ip_address',
            'user_agent',
            'created_at',
            'payload',
        ];

        fputcsv($handle, $header);

        foreach ($records as $record) {
            $row = [
                (string) ($record['id'] ?? ''),
                (string) ($record['form_id'] ?? ''),
                (string) ($record['form_title'] ?? ''),
                (string) ($record['submission_uuid'] ?? ''),
                (string) ($record['status'] ?? ''),
                (string) ($record['ip_address'] ?? ''),
                $this->sanitizeForCsv((string) ($record['user_agent'] ?? '')),
                (string) ($record['created_at'] ?? ''),
                $this->encodePayload($record['payload'] ?? []),
            ];

            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        if ($csv === false) {
            throw new RuntimeException(Text::_('COM_NXPEASYFORMS_EXPORT_STREAM_FAILED'));
        }

        return $csv;
    }

    /**
     * Encode payload data to JSON for transport inside CSV.
     *
     * @param   array<string, mixed>  $payload  Submission payload values.
     */
    private function encodePayload(array $payload): string
    {
        if ($payload === []) {
            return '';
        }

        try {
            return json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
            ) ?: '';
        } catch (JsonException $exception) {
            return '';
        }
    }

    /**
     * Strip newlines from arbitrary strings to keep CSV rows intact.
     */
    private function sanitizeForCsv(string $value): string
    {
        return trim(str_replace(["\r", "\n"], ' ', $value));
    }
}
