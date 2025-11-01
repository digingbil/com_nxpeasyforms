<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Tests\Service;

use Joomla\Component\Nxpeasyforms\Administrator\Service\Export\SubmissionExporter;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Repository\SubmissionRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SubmissionExporterTest extends TestCase
{
    public function testExportCsvReturnsResult(): void
    {
        $repository = new class () extends SubmissionRepository {
            public function __construct()
            {
                // Prevent parent constructor from requiring a database connection in tests.
            }

            public function findByIds(array $ids): array
            {
                return [[
                    'id' => 10,
                    'form_id' => 4,
                    'form_title' => 'Contact Form',
                    'submission_uuid' => 'uuid-123',
                    'status' => 'new',
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'TestAgent',
                    'created_at' => '2024-01-01 12:00:00',
                    'payload' => ['name' => 'Alice'],
                ]];
            }
        };

        $exporter = new SubmissionExporter($repository);
        $result = $exporter->export([10], 'csv');

        self::assertSame('text/csv; charset=utf-8', $result->getContentType());
        self::assertStringEndsWith('.csv', $result->getFilename());

        $body = $result->getContents();
        self::assertStringContainsString('uuid-123', $body);
        self::assertStringContainsString('Alice', $body);
    }

    public function testExportJsonReturnsResult(): void
    {
        $repository = new class () extends SubmissionRepository {
            public function __construct()
            {
                // Prevent parent constructor from requiring a database connection in tests.
            }

            public function findByIds(array $ids): array
            {
                return [[
                    'id' => 12,
                    'form_id' => 5,
                    'form_title' => 'Signup',
                    'submission_uuid' => 'uuid-456',
                    'status' => 'processed',
                    'ip_address' => null,
                    'user_agent' => null,
                    'created_at' => '2024-04-01 09:30:00',
                    'payload' => ['email' => 'user@example.com'],
                ]];
            }
        };

        $exporter = new SubmissionExporter($repository);
        $result = $exporter->export([12], 'json');

        self::assertSame('application/json', $result->getContentType());
        self::assertStringEndsWith('.json', $result->getFilename());

        $body = $result->getContents();
        self::assertStringContainsString('user@example.com', $body);
        self::assertStringContainsString('uuid-456', $body);
    }

    public function testExportWithUnsupportedFormatThrowsException(): void
    {
        $repository = new class () extends SubmissionRepository {
            public function __construct()
            {
                // Prevent parent constructor from requiring a database connection in tests.
            }

            public function findByIds(array $ids): array
            {
                return [[
                    'id' => 1,
                    'form_id' => 1,
                    'form_title' => 'Sample',
                    'submission_uuid' => 'uuid',
                    'status' => 'new',
                    'ip_address' => null,
                    'user_agent' => null,
                    'created_at' => null,
                    'payload' => [],
                ]];
            }
        };

        $exporter = new SubmissionExporter($repository);

        $this->expectException(RuntimeException::class);
        $exporter->export([1], 'xml');
    }

    public function testExportCsvIncludesAllSelectedRecords(): void
    {
        $repository = new class () extends SubmissionRepository {
            public function __construct()
            {
            }

            public function findByIds(array $ids): array
            {
                return [
                    [
                        'id' => 1,
                        'form_id' => 10,
                        'form_title' => 'Sample A',
                        'submission_uuid' => 'uuid-a',
                        'status' => 'new',
                        'ip_address' => '127.0.0.1',
                        'user_agent' => 'UA1',
                        'created_at' => '2024-05-01 08:00:00',
                        'payload' => ['field' => 'first'],
                    ],
                    [
                        'id' => 2,
                        'form_id' => 10,
                        'form_title' => 'Sample A',
                        'submission_uuid' => 'uuid-b',
                        'status' => 'new',
                        'ip_address' => '127.0.0.2',
                        'user_agent' => 'UA2',
                        'created_at' => '2024-05-02 09:00:00',
                        'payload' => ['field' => 'second'],
                    ],
                ];
            }
        };

        $exporter = new SubmissionExporter($repository);
        $result = $exporter->export([1, 2], 'csv');

        $body = $result->getContents();

        self::assertStringContainsString('uuid-a', $body);
        self::assertStringContainsString('uuid-b', $body);

        $lines = array_filter(explode("\n", trim($body)));
        self::assertCount(3, $lines, 'Header plus two data rows expected');
    }
}
