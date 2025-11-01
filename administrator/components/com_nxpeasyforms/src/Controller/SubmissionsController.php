<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Controller;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Export\SubmissionExporter;
use RuntimeException;
use Throwable;
use function array_filter;
use function array_map;
use function array_values;
use function strlen;


// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Controller for submissions list view.
 */
final class SubmissionsController extends AdminController
{
    protected $text_prefix = 'COM_NXPEASYFORMS';

    protected $view_list = 'submissions';

    /**
     * Get and return a model instance.
     *
     * This method returns a model instance from the MVC factory. Callers can
     * provide the model name, class prefix and configuration.
     *
     * @param string $name The model name. Defaults to 'Submissions'.
     * @param string $prefix The class prefix for the model.
     * @param array<string,mixed> $config Configuration options for model creation.
     *
     * @return BaseDatabaseModel
     * @since 1.0.0
     */
    public function getModel($name = 'Submissions', $prefix = 'Administrator', $config = ['ignore_request' => true]): BaseDatabaseModel
    {
        $model = parent::getModel($name, $prefix, $config);

        if (!$model instanceof BaseDatabaseModel) {
            throw new \RuntimeException(Text::sprintf('JLIB_APPLICATION_ERROR_MODEL_CREATE', $name), 500);
        }

        return $model;
    }

    /**
     * Export selected submissions using the configured exporter service.
     */
    public function export(): void
    {
        $app = Factory::getApplication();

        if (!Session::checkToken()) {
            $this->setMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_nxpeasyforms&view=submissions', false));

            return;
        }

        $user = $app->getIdentity();

        if (!$user->authorise('nxpeasyforms.export', 'com_nxpeasyforms')) {
            $this->setMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_nxpeasyforms&view=submissions', false));

            return;
        }

        $ids = array_values(
            array_filter(
                array_map('intval', (array) $this->input->post->get('cid', [], 'array')),
                static fn (int $id): bool => $id > 0
            )
        );

        if ($ids === []) {
            $this->setMessage(Text::_('COM_NXPEASYFORMS_EXPORT_NO_SELECTION'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_nxpeasyforms&view=submissions', false));

            return;
        }

        $format = $this->input->getCmd('export_format', 'csv');

        try {
            $exporter = $this->resolveSubmissionExporter();
            $result = $exporter->export($ids, $format);
        } catch (RuntimeException $exception) {
            $this->setMessage($exception->getMessage(), 'error');
            $this->setRedirect(Route::_('index.php?option=com_nxpeasyforms&view=submissions', false));

            return;
        } catch (Throwable $exception) {
            $this->setMessage(Text::_('COM_NXPEASYFORMS_EXPORT_FAILED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_nxpeasyforms&view=submissions', false));

            return;
        }

        $body = $result->getContents();

        $app->setHeader('Content-Type', $result->getContentType(), true);
        $app->setHeader('Content-Disposition', 'attachment; filename="' . $result->getFilename() . '"', true);
        $app->setHeader('Content-Length', (string) strlen($body), true);
        $app->setHeader('Pragma', 'public', true);
        $app->setHeader('Cache-Control', 'private, must-revalidate', true);
        $app->setHeader('Expires', '0', true);
        $app->sendHeaders();

        echo $body;
        $app->close();
    }

    /**
     * Resolve the submission exporter service with a cold-start fallback.
     */
    private function resolveSubmissionExporter(): SubmissionExporter
    {
        $container = Factory::getContainer();

        if (method_exists($container, 'has') && $container->has(SubmissionExporter::class)) {
            return $container->get(SubmissionExporter::class);
        }

        return new SubmissionExporter();
    }
}
