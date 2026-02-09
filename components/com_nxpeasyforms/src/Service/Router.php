<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Site\Service;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Categories\CategoryFactoryInterface;
use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\RouterViewConfiguration;
use Joomla\CMS\Component\Router\Rules\MenuRules;
use Joomla\CMS\Component\Router\Rules\NomenuRules;
use Joomla\CMS\Component\Router\Rules\StandardRules;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Factory;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Repository\FormRepository;
use Joomla\Database\DatabaseInterface;


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
        SiteApplication $app,
        AbstractMenu $menu,
        ?CategoryFactoryInterface $categoryFactory = null,
        ?DatabaseInterface $db = null
    ) {
        // Initialize FormRepository
        if ($db !== null) {
            $this->forms = new FormRepository($db);
        } else {
            $container = Factory::getContainer();
            if (method_exists($container, 'has') && $container->has(FormRepository::class)) {
                $this->forms = $container->get(FormRepository::class);
            } else {
                $this->forms = new FormRepository($container->get(DatabaseInterface::class));
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
     * Override build to ensure view/id parameters are stripped when they match a menu item.
     */
    public function build(&$query)
    {
        $segments = parent::build($query);

        // If we have an Itemid, check if the menu item matches our query exactly
        if (isset($query['Itemid'])) {
            $menu = $this->menu;
            $item = $menu->getItem($query['Itemid']);

            if ($item && isset($item->query['option']) && $item->query['option'] === 'com_nxpeasyforms') {
                // Parse the link string to get the actual query parameters
                if (isset($item->link)) {
                    parse_str(parse_url($item->link, PHP_URL_QUERY), $linkVars);
                    
                    // Remove query vars that match the menu item's link
                    foreach ($linkVars as $key => $value) {
                        if ($key === 'option' || $key === 'Itemid') {
                            continue;
                        }

                        if (isset($query[$key]) && $query[$key] == $value) {
                            unset($query[$key]);
                        }
                    }
                }
                
                // Also check the menu item's query array
                foreach ($item->query as $key => $value) {
                    if ($key === 'option' || $key === 'Itemid') {
                        continue;
                    }

                    if (isset($query[$key]) && $query[$key] == $value) {
                        unset($query[$key]);
                    }
                }
            }
        }

        // Ensure form routes never leak their view/id query variables when the menu already defines them.
        // StandardRules normally handles this, but it relies on an Itemid being present. When MenuRules fails
        // to locate a matching Itemid (for example when building links programmatically), we still want the
        // SEF URL without duplicated query data.
        if (($query['view'] ?? null) === 'form' && isset($query['id'])) {
            unset($query['view'], $query['id']);
        }

        return $segments;
    }

    /**
     * Ensure duplicate view/id pairs are stripped before Joomla's menu preprocessing runs.
     */
    public function preprocess($query)
    {
        $query = parent::preprocess($query);

        if (($query['option'] ?? '') !== 'com_nxpeasyforms' || !isset($query['Itemid'])) {
            return $query;
        }

        $item = $this->menu->getItem((int) $query['Itemid']);

        if (!$item || ($item->component ?? '') !== 'com_nxpeasyforms') {
            return $query;
        }

        $menuView = $item->query['view'] ?? null;
        $menuId   = isset($item->query['id']) ? (int) $item->query['id'] : null;

        if (($query['view'] ?? null) === $menuView && isset($query['id']) && (int) $query['id'] === $menuId) {
            unset($query['view'], $query['id']);
        }

        return $query;
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

        // Use the numeric identifier as the array key so Joomla's router rules can strip matching menu queries
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
