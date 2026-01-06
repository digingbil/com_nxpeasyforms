<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\Component\Content\Administrator\Model\ArticleModel;
use Joomla\CMS\Uri\Uri;

use function array_filter;
use function array_map;
use function explode;
use function is_array;
use function is_numeric;
use function is_scalar;
use function sprintf;
use function str_starts_with;
use function trim;
use function strip_tags;
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Dispatches Joomla article creation for form submissions.
 * @since 1.0.0
 */
final class JoomlaArticleDispatcher implements IntegrationDispatcherInterface
{
	/**
	 * Dispatches a payload to create a Joomla article with the given settings.
	 *
	 * @param   array<string,mixed>             $settings   Article creation settings and field mappings
	 * @param   array<string,mixed>             $form       Form configuration array with id and title
	 * @param   array<string,mixed>             $payload    Form submission payload data
	 * @param   array<string,mixed>             $context    Contextual information for article creation
	 * @param   array<int,array<string,mixed>>  $fieldMeta  Field metadata information
	 * @since 1.0.0
	 */
    public function dispatch(array $settings, array $form, array $payload, array $context, array $fieldMeta): void
    {
        if (empty($settings['enabled'])) {
            return;
        }

        $data = $this->buildArticleData($settings, $payload, $fieldMeta);

        if ($data['title'] === '' && $data['introtext'] === '' && $data['fulltext'] === '') {
            // Nothing meaningful to store.
            return;
        }

        try {
            $model = $this->getArticleModel();
        } catch (\Throwable $exception) {
            $this->getApplication()->enqueueMessage(
                sprintf('Failed to resolve com_content ArticleModel: %s', $exception->getMessage()),
                'warning'
            );
            return;
        }

        // Default workflow flag to published state unless workflow overrides it.
        $data['workflow_id'] = $data['workflow_id'] ?? null;

        $saved = $model->save($data);

        if ($saved === false && $model->getError()) {
            $this->getApplication()->enqueueMessage(
                sprintf('Joomla article creation failed: %s', $model->getError()),
                'warning'
            );
            return;
        }

        // Defensive post-save fix: if form filtering dropped images, force-persist JSON to the table.
        if ($saved !== false && !empty($data['images'])) {
            try {
                /** @var ArticleModel $model */
                $table = $model->getTable();

                // Obtain the ID of the just-saved article
                $id = (int) ($table->id ?? 0);
                if ($id > 0) {
                    // Reload to ensure we have the latest persisted values
                    $table->load($id);

                    $currentImages = (string) ($table->images ?? '');

                    if ($currentImages === '' || $currentImages === '{}') {
                        $imagesJson = is_array($data['images'])
                            ? json_encode($data['images'], JSON_UNESCAPED_SLASHES)
                            : (string) $data['images'];

                        if (is_string($imagesJson) && $imagesJson !== '') {
                            $table->images = $imagesJson;
                            // Best-effort store; on failure we log a warning but don't hard-fail submission flow
                            if (!$table->store()) {
                                $this->getApplication()->enqueueMessage(
                                    'Article saved but failed to persist intro/full images to the images column.',
                                    'warning'
                                );
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Non-fatal: keep submission successful but surface an admin-side notice
                $this->getApplication()->enqueueMessage(
                    sprintf('Article saved but image metadata may be missing: %s', $e->getMessage()),
                    'warning'
                );
            }
        }
    }

    /**
     * Normalizes raw settings into a Joomla article payload.
     *
     * @param array<string,mixed> $settings
     * @param array<string,mixed> $payload
     * @param array<int,array<string,mixed>> $fieldMeta
     *
     * @return array<string,mixed>
     * @since 1.0.0
     */
    private function buildArticleData(array $settings, array $payload, array $fieldMeta): array
    {
        $map = is_array($settings['map'] ?? null) ? $settings['map'] : [];

        // Support legacy integration keys when migrating existing forms.
        $introKey = (string) ($map['introtext'] ?? $map['content'] ?? '');
        $fullKey = (string) ($map['fulltext'] ?? '');
        $excerptKey = (string) ($map['excerpt'] ?? '');

        $title = $this->stringValue($payload, (string) ($map['title'] ?? ''));
        $introtext = $this->stringValue($payload, $introKey, allowHtml: true);
        $fulltext = $this->stringValue($payload, $fullKey, allowHtml: true);

        if ($fulltext === '' && $introtext === '' && $excerptKey !== '') {
            // Use excerpt for intro text if nothing else supplied.
            $introtext = $this->stringValue($payload, $excerptKey, allowHtml: true);
        }

        $categoryId = $this->resolveCategoryId($settings);
        $state = $this->mapStatus((string) ($settings['status'] ?? $settings['post_status'] ?? 'unpublished'));
        $createdBy = $this->resolveAuthorId($settings);

        $data = [
            'id' => 0,
            'catid' => $categoryId,
            'title' => $title,
            'introtext' => $introtext,
            'fulltext' => $fulltext,
            'state' => $state,
            'language' => (string) ($settings['language'] ?? '*'),
            'access' => (int) ($settings['access'] ?? 1),
            'created_by' => $createdBy,
            'created_by_alias' => $createdBy === 0 ? ($settings['created_by_alias'] ?? '') : '',
            'alias' => $this->stringValue($payload, (string) ($map['alias'] ?? '')),
            'tags' => $this->resolveTags($payload, (string) ($map['tags'] ?? '')),
        ];

        $featuredKey = (string) ($map['featured_image'] ?? '');

        if ($featuredKey !== '') {
            $imagePath = $this->stringValue($payload, $featuredKey);
            $fileMeta = $this->resolveFileMeta($fieldMeta, $featuredKey);

            $relative = $this->normaliseImagePath(
                $imagePath,
                is_array($fileMeta) ? ($fileMeta['path'] ?? null) : null,
                is_array($fileMeta) ? ($fileMeta['url'] ?? null) : null
            );

            if ($relative !== '') {
                $altKey = (string) ($map['featured_image_alt'] ?? '');
                $captionKey = (string) ($map['featured_image_caption'] ?? '');

                $imagesData = [
                    'image_intro' => $relative,
                    'image_intro_alt' => $this->stringValue($payload, $altKey) ?: $title,
                    'image_intro_caption' => $this->stringValue($payload, $captionKey),
                    'image_intro_class' => '',
                    'image_intro_image' => '',
                    'float_intro' => '',
                    'image_fulltext' => $relative,
                    'image_fulltext_alt' => $this->stringValue($payload, $altKey) ?: $title,
                    'image_fulltext_caption' => '',
                    'image_fulltext_class' => '',
                    'float_fulltext' => '',
                ];

                // Pass structured images array to align with com_content article form expectations.
                $data['images'] = $imagesData;
            }
        }

        if ($data['tags'] === []) {
            unset($data['tags']);
        }

        return $data;
    }
	
	/**
	 * Resolves the category ID for a Joomla article.
	 * @param array<string,mixed> $settings
	 * @return int
	 * @since 1.0.0
	 */
    private function resolveCategoryId(array $settings): int
    {
        $categoryId = 0;

        if (isset($settings['category_id'])) {
            $categoryId = (int) $settings['category_id'];
        } elseif (isset($settings['post_type']) && is_numeric($settings['post_type'])) {
            // Legacy field reuse.
            $categoryId = (int) $settings['post_type'];
        }

        if ($categoryId <= 0) {
            $categoryId = (int) ComponentHelper::getParams('com_content')->get('default_category', 0);
        }

        // Fallback to Uncategorised (ID 2 in core installs) if still unset.
        return $categoryId > 0 ? $categoryId : 2;
    }
	
	/**
	 * Maps a status string to a Joomla article state.
	 * @param string $status
	 * @return int
	 * @since 1.0.0
	 */
    private function mapStatus(string $status): int
    {
        return match ($status) {
            'published', 'publish' => 1,
            'archived', 'archive' => 2,
            'trashed', 'trash' => -2,
            default => 0,
        };
    }
	
	/**
	 * Resolves the author ID for a Joomla article.
	 * @param array<string,mixed> $settings
	 * @return int
	 * @since 1.0.0
	 */
    private function resolveAuthorId(array $settings): int
    {
        $mode = (string) ($settings['author_mode'] ?? 'none');

        return match ($mode) {
            'fixed' => (int) ($settings['fixed_author_id'] ?? 0),
            'anonymous', 'none' => 0,
            default => $this->getApplication()->getIdentity()->id ?? 0,
        };
    }
	
	/**
	 * Resolves tags from a form submission payload.
	 * @param array<string,mixed> $payload
	 * @param string $field
	 * @return array<int,string>
	 * @since 1.0.0
	 */
    private function resolveTags(array $payload, string $field): array
    {
        if ($field === '' || !isset($payload[$field])) {
            return [];
        }

        $value = $payload[$field];
        $raw = is_array($value) ? $value : explode(',', (string) $value);

        $tags = array_map(
            static function ($item) {
                if (is_numeric($item)) {
                    $id = (int) $item;

                    return $id > 0 ? $id : null;
                }

                if (!is_scalar($item)) {
                    return null;
                }

                $name = trim((string) $item);

                if ($name === '') {
                    return null;
                }

                if (str_starts_with($name, '#new#') || str_starts_with($name, '#existing#')) {
                    return $name;
                }

                return '#new#' . $name;
            },
            $raw
        );

        $tags = array_filter($tags, static fn ($tag) => $tag !== null);

        return array_values($tags);
    }

    /**
     * Resolve file meta information for a given field.
     *
     * @param array<int,array<string,mixed>> $fieldMeta
     * @param string $fieldName
     *
     * @return array<string,mixed>|null
     * @since 1.0.0
     */
    private function resolveFileMeta(array $fieldMeta, string $fieldName): ?array
    {
        foreach ($fieldMeta as $entry) {
            if (($entry['name'] ?? null) === $fieldName) {
                return is_array($entry['meta'] ?? null) ? $entry['meta'] : null;
            }
        }

        return null;
    }

    /**
     * Retrieves a string representation of a field's value from the payload.
     *
     * @param   array<string,mixed>  $payload    The data payload containing field values
     * @param   string               $field      The field name to be retrieved from the payload
	 * @param   bool                 $allowHtml  Whether to allow HTML content in the value
	 *
	 * @return string                          The string representation of the field's value, sanitized if necessary
	 * @since 1.0.0
	 */
    private function stringValue(array $payload, string $field, bool $allowHtml = false): string
    {
        if ($field === '' || !isset($payload[$field])) {
            return '';
        }

        $value = $payload[$field];

        if (is_array($value)) {
            $value = implode(', ', array_map(static fn ($item) => is_scalar($item) ? (string) $item : '', $value));
        }

        if (!is_scalar($value)) {
            return '';
        }

        $string = trim((string) $value);

        if ($allowHtml) {
            return $string;
        }

        return strip_tags($string);
    }

    /**
     * Normalise image paths to relative Joomla media paths.
     *
     * @param string $value
     * @param string|null $metaPath
     *
     * @return string
     * @since 1.0.0
     */
    private function normaliseImagePath(string $value, ?string $metaPath, ?string $metaUrl = null): string
    {
        $candidate = $metaPath ?? ($metaUrl ?? $value);
        $candidate = trim($candidate);

        if ($candidate === '') {
            return '';
        }

        $root = Uri::root();
        $rootRelative = Uri::root(true);

        if ($root !== '' && str_starts_with($candidate, $root)) {
            $candidate = substr($candidate, strlen($root));
        } elseif ($rootRelative !== '' && str_starts_with($candidate, $rootRelative)) {
            $candidate = substr($candidate, strlen($rootRelative));
        }

        return ltrim($candidate, '/');
    }

	/**
	 * Retrieves an instance of the com_content ArticleModel.
	 *
	 * @return ArticleModel Returns the ArticleModel instance for managing Joomla articles.
	 * @throws \Exception
	 * @since 1.0.0
	 */
    private function getArticleModel(): ArticleModel
    {
        /** @var CMSApplicationInterface $app */
        $app = Factory::getApplication();

        $component = $app->bootComponent('com_content');
        $factory = $component->getMVCFactory();

        // Ensure the form can find the article.xml file when called from API/frontend context
        Form::addFormPath(\JPATH_ADMINISTRATOR . '/components/com_content/forms');
        Form::addFormPath(\JPATH_ADMINISTRATOR . '/components/com_content/models/forms');

        /** @var ArticleModel $model */
        $model = $factory->createModel('Article', 'Administrator', ['ignore_request' => true]);

        return $model;
    }

	/**
	 * Gets the Joomla application instance.
	 *
	 * @return CMSApplicationInterface Returns the application object
	 * @throws \Exception If application cannot be retrieved
	 * @since 1.0.0
	 */
	private function getApplication(): CMSApplicationInterface
    {
        return Factory::getApplication();
    }
}
