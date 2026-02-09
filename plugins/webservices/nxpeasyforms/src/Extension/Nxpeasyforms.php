<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */
declare(strict_types=1);

namespace Joomla\Plugin\WebServices\Nxpeasyforms\Extension;

use Joomla\CMS\Event\Application\BeforeApiRouteEvent;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use Joomla\Router\Route;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Web services adapter that exposes the NXP Easy Forms submission endpoint.
 */
final class Nxpeasyforms extends CMSPlugin implements SubscriberInterface
{
    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onBeforeApiRoute' => 'onBeforeApiRoute',
        ];
    }

    public function onBeforeApiRoute(BeforeApiRouteEvent $event): void
    {
        $router = $event->getRouter();

        $routes = [
            new Route(
                ['POST'],
                'v1/nxpeasyforms/submission',
                'submission.create',
                [],
                [
                    'component' => 'com_nxpeasyforms',
                    'public' => true,
                ]
            ),
            new Route(
                ['GET'],
                'v1/nxpeasyforms/utility/countries',
                'utility.countries',
                [],
                [
                    'component' => 'com_nxpeasyforms',
                    'public' => true,
                ]
            ),
            new Route(
                ['GET'],
                'v1/nxpeasyforms/utility/states/:country',
                'utility.states',
                ['country' => '[A-Z]{2}'],
                [
                    'component' => 'com_nxpeasyforms',
                    'public' => true,
                ]
            ),
        ];

        $router->addRoutes($routes);
    }
}
