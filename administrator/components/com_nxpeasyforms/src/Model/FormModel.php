<?php

declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Model;

use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;
use Joomla\Component\Nxpeasyforms\Administrator\Table\FormTable;

use function is_array;
use function is_object;
use function json_encode;

use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_UNICODE;

/**
 * Administrator model for a single form.
 */
final class FormModel extends AdminModel
{
    protected $text_prefix = 'COM_NXPEASYFORMS';

    /**
     * {@inheritDoc}
     */
    public function getTable($type = 'Form', $prefix = 'Joomla\\Component\\Nxpeasyforms\\Administrator\\Table\\', $config = []): Table
    {
        return Table::getInstance($type, $prefix, $config);
    }

    /**
     * {@inheritDoc}
     *
     * @return \stdClass|null
     */
    public function getItem($pk = null)
    {
        /** @var \stdClass|null $item */
        $item = parent::getItem($pk);

        if ($item === null) {
            return null;
        }

        $item->fields = $this->decodeJsonProperty($item->fields ?? '[]');
        $item->settings = $this->decodeJsonProperty($item->settings ?? '{}');

        return $item;
    }

    /**
     * {@inheritDoc}
     *
     * @param array<int|string,mixed> $data
     */
    public function save($data)
    {
        if (!isset($data['fields'])) {
            $data['fields'] = [];
        } elseif (is_string($data['fields']) && $data['fields'] !== '') {
            $data['fields'] = $this->decodeJsonProperty($data['fields']);
        }

        if (!isset($data['settings'])) {
            $data['settings'] = [];
        } elseif (is_string($data['settings']) && $data['settings'] !== '') {
            $data['settings'] = $this->decodeJsonProperty($data['settings']);
        }

        return parent::save($data);
    }

    /**
     * {@inheritDoc}
     */
    protected function loadFormData()
    {
        $data = parent::loadFormData();

        if (is_object($data)) {
            $data = (array) $data;
        }

        if (!is_array($data)) {
            return $data;
        }

        $data['fields'] = $this->stringify($data['fields'] ?? [], '[]');
        $data['settings'] = $this->stringify($data['settings'] ?? [], '{}');

        return $data;
    }

    /**
     * {@inheritDoc}
     */
    protected function prepareTable($table)
    {
        if ($table instanceof FormTable && $table->title !== '') {
            $table->title = trim($table->title);
        }
    }

    /**
     * @param string $jsonPayload JSON encoded value.
     *
     * @return array<mixed>
     */
    private function decodeJsonProperty(string $jsonPayload): array
    {
        if ($jsonPayload === '') {
            return [];
        }

        try {
            $decoded = json_decode($jsonPayload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException(
                'COM_NXPEASYFORMS_ERROR_JSON_DECODE_FORM_ITEM',
                0,
                $exception
            );
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Normalises payloads for hidden JSON fields.
     *
     * @param mixed $payload Raw payload retrieved from the table/session.
     */
    private function stringify($payload, string $fallback): string
    {
        if (is_string($payload)) {
            return $payload;
        }

        try {
            return json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
            );
        } catch (\JsonException $exception) {
            return $fallback;
        }
    }
}
