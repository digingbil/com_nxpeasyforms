<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Ajax\Handler\Settings;

use Joomla\CMS\Language\Text;
use Joomla\Component\Nxpeasyforms\Administrator\Ajax\AjaxRequestContext;
use Joomla\Component\Nxpeasyforms\Administrator\Ajax\AjaxResult;
use Joomla\Component\Nxpeasyforms\Administrator\Ajax\Support\CategoryProvider;
use Joomla\Component\Nxpeasyforms\Administrator\Ajax\Support\PermissionGuard;
use RuntimeException;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Handles Joomla-specific settings AJAX actions (categories, etc.).
 */
final class JoomlaSettingsAjaxHandler
{
    /**
     * @var PermissionGuard
     */
    private PermissionGuard $guard;

    /**
     * @var CategoryProvider
     */
    private CategoryProvider $categories;

    /**
     * @param PermissionGuard $guard Performs ACL assertions.
     * @param CategoryProvider $categories Provides Joomla content category lists.
     */
    public function __construct(PermissionGuard $guard, CategoryProvider $categories)
    {
        $this->guard = $guard;
        $this->categories = $categories;
    }

    /**
     * Handle Joomla settings resource routing.
     *
     * @param AjaxRequestContext $context Current request context.
     * @param string $action Action segment under settings/joomla.
     * @param string $method HTTP method used for the request.
     *
     * @return AjaxResult
     */
    public function dispatch(AjaxRequestContext $context, string $action, string $method): AjaxResult
    {
        return match ($action) {
            'categories' => $method === 'GET'
                ? $this->getCategories()
                : throw new RuntimeException(Text::_('JERROR_PAGE_NOT_FOUND'), 404),
            default => throw new RuntimeException(Text::_('JERROR_PAGE_NOT_FOUND'), 404),
        };
    }

    /**
     * Provide a list of Joomla content categories formatted for select inputs.
     *
     * @return AjaxResult
     */
    private function getCategories(): AjaxResult
    {
        $this->guard->assertAuthorised('core.manage');

        try {
            $categories = $this->categories->fetchContentCategories();
        } catch (RuntimeException $exception) {
            return new AjaxResult([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 500);
        }

        return new AjaxResult([
            'success' => true,
            'categories' => $categories,
        ]);
    }
}
