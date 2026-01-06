<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
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
 *
 * Manages loading, saving and preparing form data for the component's
 * form builder user interface.
 *
 * @since 1.0.0
 */
final class FormModel extends AdminModel
{
    protected $text_prefix = 'COM_NXPEASYFORMS';

    /**
     * Load and return a form instance from XML definition.
     *
     * @param array<string,mixed> $data The data to bind to the form (optional).
     * @param bool $loadData Whether to load data from the model state.
     *
     * @return \Joomla\CMS\Form\Form|false Form object, or false on failure.
     * @since 1.0.0
     */
    public function getForm($data = [], $loadData = true)
    {
        $form = $this->loadForm(
            'com_nxpeasyforms.form',
            'form',
            ['control' => 'jform', 'load_data' => $loadData]
        );

        if (empty($form)) {
            return false;
        }

        return $form;
    }

    /**
     * Return a Table object for the given type and prefix.
     *
     * @param string $type The type of table to instantiate.
     * @param string $prefix The prefix for the table class name.
     * @param array<string,mixed> $config Configuration options for the table.
     *
     * @return \Joomla\CMS\Table\Table
     * @since 1.0.0
     */
    public function getTable($type = 'FormTable', $prefix = 'Joomla\\Component\\Nxpeasyforms\\Administrator\\Table\\', $config = [])
    {
        return Table::getInstance($type, $prefix, $config);
    }

    /**
     * Retrieve an item by primary key after decoding JSON properties.
     *
     * @param int|null $pk The primary key value to load.
     *
     * @return \stdClass|null The loaded item object or null if not found.
     * @since 1.0.0
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
     * Save form data to the database.
     *
     * Accepts an array of form data and saves it via the parent model,
     * handling JSON encoding for complex fields.
     *
     * @param array<int|string,mixed> $data The form data to save.
     *
     * @return bool True on success, false on failure.
     * @since 1.0.0
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
     * Load form data from model state and session.
     *
     * Prepares form data for presentation, stringifying array fields
     * to JSON for form rendering.
     *
     * @return array<string,mixed>|object Form data.
     * @since 1.0.0
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
     * Prepare a table instance before saving.
     *
     * Trims the title and ensures the form is valid before storage.
     *
     * @param FormTable $table The table instance to prepare.
     *
     * @return void
     * @since 1.0.0
     */
    protected function prepareTable($table)
    {
        if ($table instanceof FormTable && $table->title !== '') {
            $table->title = trim($table->title);
        }
    }

    /**
     * Decode a JSON property value to an array.
     *
     * @param string $jsonPayload JSON encoded value.
     *
     * @return array<mixed> Decoded array, or empty array on failure.
     *
     * @throws \RuntimeException When JSON decoding fails.
     * @since 1.0.0
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
     * Normalize payloads for hidden JSON fields.
     *
     * Converts mixed payloads to JSON strings, with a fallback value
     * when encoding fails.
     *
     * @param mixed $payload Raw payload retrieved from the table/session.
     * @param string $fallback JSON fallback value when encoding fails.
     *
     * @return string JSON encoded payload or fallback.
     * @since 1.0.0
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
