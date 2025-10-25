<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Model;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\QueryInterface;


// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Model for listing submissions.
 */
final class SubmissionsModel extends ListModel
{
    protected $filterFields = [
        'id',
        'form_id',
        'status',
        'created_at',
        'submission_uuid',
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

        $formId = $app->getUserStateFromRequest($context . '.filter.form_id', 'filter_form_id', 0, 'int');
        $this->setState('filter.form_id', $formId > 0 ? $formId : 0);

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
                $db->quoteName('a.form_id'),
                $db->quoteName('a.submission_uuid'),
                $db->quoteName('a.status'),
                $db->quoteName('a.ip_address'),
                $db->quoteName('a.created_at'),
                $db->quoteName('f.title', 'form_title'),
            ])
            ->from($db->quoteName('#__nxpeasyforms_submissions', 'a'))
            ->join(
                'LEFT',
                $db->quoteName('#__nxpeasyforms_forms', 'f'),
                $db->quoteName('f.id') . ' = ' . $db->quoteName('a.form_id')
            );

        $search = $this->getState('filter.search');
        if ($search !== '') {
            $searchTerm = '%' . str_replace(' ', '%', $search) . '%';
            $query->where(
                '('
                . $db->quoteName('a.submission_uuid') . ' LIKE ' . $db->quote($searchTerm)
                . ' OR ' . $db->quoteName('f.title') . ' LIKE ' . $db->quote($searchTerm)
                . ')'
            );
        }

        $status = $this->getState('filter.status', 'all');
        if ($status !== 'all' && $status !== '') {
            $query->where($db->quoteName('a.status') . ' = ' . $db->quote($status));
        }

        $formId = (int) $this->getState('filter.form_id', 0);
        if ($formId > 0) {
            $query->where($db->quoteName('a.form_id') . ' = ' . $db->quote($formId));
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
            'form_id' => 'a.form_id',
            'status' => 'a.status',
            'created_at' => 'a.created_at',
            'submission_uuid' => 'a.submission_uuid',
        ];

        $query->order($db->quoteName($columnMap[$orderCol]) . ' ' . $orderDir);

        return $query;
    }
}
