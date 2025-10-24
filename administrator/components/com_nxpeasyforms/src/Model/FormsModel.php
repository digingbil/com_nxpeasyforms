<?php

declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Model;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\QueryInterface;

/**
 * Model for listing form definitions.
 */
final class FormsModel extends ListModel
{
    protected $filterFields = [
        'id',
        'title',
        'active',
        'created_at',
        'updated_at',
    ];

    /**
     * {@inheritDoc}
     */
    protected function populateState($ordering = 'created_at', $direction = 'desc')
    {
        $app = Factory::getApplication();
        $context = $this->context;

        $search = $app->getUserStateFromRequest($context . '.filter.search', 'filter_search', '', 'string');
        $this->setState('filter.search', trim($search));

        $status = $app->getUserStateFromRequest($context . '.filter.status', 'filter_status', 'all', 'string');
        $this->setState('filter.status', $status ?: 'all');

        parent::populateState($ordering, $direction);
    }

    /**
     * {@inheritDoc}
     */
    protected function getListQuery(): QueryInterface
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('a.id'),
                $db->quoteName('a.title'),
                $db->quoteName('a.active'),
                $db->quoteName('a.created_at'),
                $db->quoteName('a.updated_at'),
            ])
            ->from($db->quoteName('#__nxpeasyforms_forms', 'a'));

        $search = $this->getState('filter.search');
        if ($search !== '') {
            $searchTerm = '%' . str_replace(' ', '%', $search) . '%';
            $query->where(
                $db->quoteName('a.title') . ' LIKE ' . $db->quote($searchTerm)
            );
        }

        $status = $this->getState('filter.status', 'all');
        if ($status === 'active') {
            $query->where($db->quoteName('a.active') . ' = 1');
        } elseif ($status === 'inactive') {
            $query->where($db->quoteName('a.active') . ' = 0');
        }

        $orderCol = $this->state->get('list.ordering', 'created_at');
        $orderDir = strtoupper($this->state->get('list.direction', 'DESC'));

        if (!in_array($orderCol, $this->filterFields, true)) {
            $orderCol = 'created_at';
        }

        if (!in_array($orderDir, ['ASC', 'DESC'], true)) {
            $orderDir = 'DESC';
        }

        $columnMap = [
            'id' => 'a.id',
            'title' => 'a.title',
            'active' => 'a.active',
            'created_at' => 'a.created_at',
            'updated_at' => 'a.updated_at',
        ];

        $query->order($db->quoteName($columnMap[$orderCol]) . ' ' . $orderDir);

        return $query;
    }
}
