<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Site\Service;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\RouterViewConfiguration;
use Joomla\CMS\Component\Router\Rules\MenuRules;
use Joomla\CMS\Component\Router\Rules\NomenuRules;
use Joomla\CMS\Component\Router\Rules\StandardRules;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\CMS\Factory;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Repository\FormRepository;
use Joomla\Database\DatabaseDriver;


// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Router providing SEF URLs for the site component.
 *
 * Generates routes in the form /form/{id}-{slug}.
 */
final class Router extends RouterView
{
    private FormRepository $forms;

    public function __construct(
        ?SiteApplication $app = null,
        ?AbstractMenu $menu = null,
        ?FormRepository $forms = null
    ) {
        if ($forms !== null) {
            $this->forms = $forms;
        } else {
            $container = Factory::getContainer();

            if (method_exists($container, 'has') && $container->has(FormRepository::class)) {
                $this->forms = $container->get(FormRepository::class);
            } else {
                /** @var DatabaseDriver $db */
                $db = $container->get(DatabaseDriver::class);
                $this->forms = new FormRepository($db);
            }
        }

        $view = new RouterViewConfiguration('form');
        $view->setKey('id');
        $this->registerView($view);

        parent::__construct($app, $menu);

        $this->attachRule(new MenuRules($this));
        $this->attachRule(new StandardRules($this));
        $this->attachRule(new NomenuRules($this));
    }

    /**
     * Builds the segment for the form view.
     *
     * @param   string|int  $id     Form identifier.
     * @param   array       $query  Current query array.
     *
     * @return  array<int|string, string>
     */
    protected function getFormSegment($id, $query): array
    {
        $id = (int) $id;
        $segment = (string) $id;

        if ($id > 0) {
            $form = $this->forms->find($id);

            if (!empty($form['title'])) {
                $slugSource = $form['alias'] ?? '';

                if ($slugSource === '') {
                    $slugSource = (string) $form['title'];
                }

                $slug = OutputFilter::stringURLSafe((string) $slugSource);

                if ($slug !== '') {
                    $segment = sprintf('%d-%s', $id, $slug);
                }
            }
        }

        return [$id => $segment];
    }

    /**
     * Parses the segment back to a form identifier.
     *
     * @param   string  $segment  URL segment.
     * @param   array   $query    Current query array.
     *
     * @return  int
     */
    protected function getFormId($segment, $query): int
    {
        if (is_array($segment)) {
            $segment = reset($segment);
        }

        if (strpos((string) $segment, '-') !== false) {
            [$id] = explode('-', (string) $segment, 2);

            return (int) $id;
        }

        return (int) $segment;
    }
}
