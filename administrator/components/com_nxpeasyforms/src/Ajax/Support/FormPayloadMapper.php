<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Ajax\Support;

use Joomla\CMS\Language\Text;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Repository\FormRepository;
use Throwable;
use function is_array;
use function is_string;
use function trim;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Maps AJAX payloads to form table structures and vice versa.
 */
final class FormPayloadMapper
{
    /**
     * @var FormRepository
     */
    private FormRepository $forms;

    /**
     * @var FormOptionsNormalizer
     */
    private FormOptionsNormalizer $normalizer;

    /**
     * @param FormRepository $forms Repository used to load existing form options.
     * @param FormOptionsNormalizer $normalizer Normaliser for form option payloads.
     */
    public function __construct(FormRepository $forms, FormOptionsNormalizer $normalizer)
    {
        $this->forms = $forms;
        $this->normalizer = $normalizer;
    }

    /**
     * Map a client payload into the data structure expected by the administrator model.
     *
     * @param array<string,mixed> $payload Incoming payload from the client.
     * @param int|null $id Optional identifier of an existing form record.
     *
     * @return array<string,mixed> Data suitable for model save operations.
     */
    public function mapPayloadToTable(array $payload, ?int $id = null): array
    {
        $config = is_array($payload['config'] ?? null) ? $payload['config'] : [];
        $fields = is_array($config['fields'] ?? null) ? $config['fields'] : [];
        $options = is_array($config['options'] ?? null) ? $config['options'] : [];

        $existingOptions = [];

        if ($id !== null) {
            try {
                $existingForm = $this->forms->find($id);

                if (is_array($existingForm)) {
                    $config = $existingForm['config'] ?? null;
                    $optionsConfig = is_array($config) ? ($config['options'] ?? null) : null;

                    if (is_array($optionsConfig)) {
                        $existingOptions = $optionsConfig;
                    }
                }
            } catch (Throwable $exception) {
                $existingOptions = [];
            }
        }

        $options = $this->normalizer->normalizeForStorage($options, $existingOptions);

        $data = [
            'title' => is_string($payload['title'] ?? null) ? trim($payload['title']) : '',
            'alias' => is_string($payload['alias'] ?? null) ? trim($payload['alias']) : '',
            'fields' => $fields,
            'settings' => $options,
        ];

        if (isset($payload['active'])) {
            $data['active'] = (int) (!empty($payload['active']));
        }

        if ($id !== null) {
            $data['id'] = $id;
        }

        return $data;
    }

    /**
     * Transform a model item into the JSON payload expected by the administrator client.
     *
     * @param object|null $item The model item fetched from the MVC layer.
     *
     * @return array<string,mixed> Normalised payload ready for JSON encoding.
     */
    public function transformForm(?object $item): array
    {
        if ($item === null) {
            throw new \RuntimeException(Text::_('COM_NXPEASYFORMS_ERROR_FORM_NOT_FOUND'), 404);
        }

        $fields = is_array($item->fields ?? null) ? $item->fields : [];
        $settings = is_array($item->settings ?? null) ? $item->settings : [];
        $settings = $this->normalizer->normalizeForClient($settings);

        return [
            'id' => (int) ($item->id ?? 0),
            'title' => (string) ($item->title ?? Text::_('COM_NXPEASYFORMS_UNTITLED_FORM')),
            'alias' => (string) ($item->alias ?? ''),
            'active' => (int) ($item->active ?? 1),
            'config' => [
                'fields' => $fields,
                'options' => $settings,
            ],
            'created_at' => $item->created_at ?? null,
            'updated_at' => $item->updated_at ?? null,
        ];
    }
}
