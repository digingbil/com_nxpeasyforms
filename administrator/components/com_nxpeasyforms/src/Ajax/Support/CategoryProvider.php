<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Ajax\Support;

use Joomla\CMS\Language\Text;
use RuntimeException;
use function is_array;
use function max;
use function str_repeat;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Provides Joomla content categories for AJAX consumers.
 */
final class CategoryProvider
{
    /**
     * @var object
     */
    private $db;

    /**
     * @param object $db Database connection used to query categories.
     */
    public function __construct(object $db)
    {
        $this->db = $db;
    }

    /**
     * Fetch a list of published content categories formatted for select inputs.
     *
     * @return array<int,array<string,mixed>> Ordered list of category data (id, title).
     */
    public function fetchContentCategories(): array
    {
        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('id'),
                $this->db->quoteName('title'),
                $this->db->quoteName('level'),
            ])
            ->from($this->db->quoteName('#__categories'))
            ->where($this->db->quoteName('extension') . ' = :extension')
            ->where($this->db->quoteName('published') . ' != -2')
            ->order($this->db->quoteName('lft') . ' ASC')
            ->bind(':extension', 'com_content');

        try {
            $this->db->setQuery($query);
            $rows = (array) $this->db->loadAssocList();
        } catch (RuntimeException $exception) {
            throw new RuntimeException(
                Text::_('COM_NXPEASYFORMS_ERROR_CATEGORIES_LOAD_FAILED'),
                500,
                $exception
            );
        }

        $formatted = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = (int) ($row['id'] ?? 0);

            if ($id <= 0) {
                continue;
            }

            $level = max(0, (int) ($row['level'] ?? 0) - 1);
            $prefix = str_repeat('— ', $level);

            $formatted[] = [
                'id' => $id,
                'title' => $prefix . (string) ($row['title'] ?? Text::_('JGLOBAL_CATEGORY_UNKNOWN')),
            ];
        }

        return $formatted;
    }
}
