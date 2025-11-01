<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Model;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Repository\SubmissionRepository;
use Joomla\Database\QueryInterface;
use Throwable;


// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Model for listing submissions.
 *
 * Handles filtering, sorting and pagination for form submissions
 * in the administrator submissions view.
 *
 * @since 1.0.0
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
     * Populate the model state with filtering and sorting parameters.
     *
     * Reads user state from the application (search, status, form filter)
     * and initializes pagination with the provided ordering defaults.
     *
     * @param string $ordering  The default ordering column.
     * @param string $direction The default sort direction ('asc' or 'desc').
     *
     * @return void
     * @since 1.0.0
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
     * Build a database query to fetch filtered and sorted submission records.
     *
     * Constructs and returns a query for the submissions table with applied filters
     * and sorting based on the model's state. Includes a join to the forms table
     * to display form titles.
     *
     * @return \Joomla\Database\QueryInterface The constructed query.
     * @since 1.0.0
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

    /**
     * Delete submissions by their identifiers.
     *
     * @param   array<int>|null  $pks  Selected primary keys (passed by reference).
     *
     * @return bool True when the operation succeeds or there is nothing to delete.
     *
     * @since 1.0.0
     */
    public function delete(&$pks): bool
    {
        $ids = array_values(
            array_filter(
                array_map('intval', (array) $pks),
                static fn (int $id): bool => $id > 0
            )
        );

        if ($ids === []) {
            $pks = [];

            return true;
        }

        try {
            $repository = $this->resolveSubmissionRepository();
            $repository->deleteByIds($ids);
        } catch (Throwable $exception) {
            $this->setError($exception->getMessage());

            return false;
        }

        $pks = $ids;

        return true;
    }

    /**
     * Resolve the submission repository via the DI container with a fallback for cold boots.
     */
    private function resolveSubmissionRepository(): SubmissionRepository
    {
        $container = Factory::getContainer();

        if (method_exists($container, 'has') && $container->has(SubmissionRepository::class)) {
            return $container->get(SubmissionRepository::class);
        }

        return new SubmissionRepository();
    }
}
