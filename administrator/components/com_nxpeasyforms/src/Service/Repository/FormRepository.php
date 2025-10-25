<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Service\Repository;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;


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
 * Provides access to form definitions with decoded configuration payload.
 */
class FormRepository
{
    private DatabaseDriver $db;

    public function __construct(?DatabaseDriver $db = null)
    {
        $this->db = $db ?? Factory::getContainer()->get(DatabaseDriver::class);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('id'),
                $this->db->quoteName('title'),
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
            'active' => (int) ($row['active'] ?? 1),
            'date' => $row['created_at'] ?? null,
            'config' => [
                'fields' => is_array($fields) ? $fields : [],
                'options' => is_array($settings) ? $settings : [],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $config
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
     * @return mixed
     */
    private function decodeJson(?string $json, $default)
    {
        if ($json === null || $json === '') {
            return $default;
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            return $decoded;
        } catch (\JsonException $exception) {
            return $default;
        }
    }
}
