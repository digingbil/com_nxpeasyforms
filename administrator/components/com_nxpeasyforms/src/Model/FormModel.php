<?php

declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Model;

use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;
use Joomla\Component\Nxpeasyforms\Administrator\Table\FormTable;

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
        }

        if (!isset($data['settings'])) {
            $data['settings'] = [];
        }

        return parent::save($data);
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
}
