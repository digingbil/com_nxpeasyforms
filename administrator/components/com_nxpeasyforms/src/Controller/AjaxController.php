<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Controller;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\Component\Nxpeasyforms\Administrator\Ajax\AjaxRequestContext;
use Joomla\Component\Nxpeasyforms\Administrator\Ajax\AjaxRouter;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Email\EmailService;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Repository\FormRepository;
use Throwable;
use function http_response_code;
use function is_object;
use function dirname;
use function explode;
use function is_file;
use function rawurldecode;
use function json_encode;
use function strtoupper;
use function trim;
use function method_exists;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Internal AJAX controller delegating to modular handlers.
 */
final class AjaxController extends BaseController
{
    /**
     * Cached router instance for the current request lifecycle.
     *
     * @var AjaxRouter|null
     */
    private ?AjaxRouter $router = null;

    /**
     * Route the AJAX request using the modular router infrastructure.
     *
     * @return void
     */
    public function route(): void
    {
        $app = Factory::getApplication();
        $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);

        $this->bootDomainServices();

    $input = $app->getInput();
    $path = trim(rawurldecode((string) $input->getString('path', '')), '/');
    $segments = $path === '' ? [] : explode('/', $path);
    $method = strtoupper($input->getMethod());
    $context = new AjaxRequestContext($input, $app);

        try {
            $result = $this->getRouter()->dispatch($context, $segments, $method);
            $status = $result->getStatus();
            $payload = $result->getData();
        } catch (Throwable $exception) {
            $status = (int) $exception->getCode();

            if ($status < 100 || $status >= 600) {
                $status = 500;
            }

            $payload = [
                'success' => false,
                'message' => $exception->getMessage(),
            ];
        }

        $encoded = json_encode($payload);

        if ($encoded === false) {
            http_response_code(500);
            echo json_encode(
                [
                    'success' => false,
                    'message' => 'Failed to encode response payload.',
                ]
            );
        } else {
            http_response_code($status);
            echo $encoded;
        }
        $app->close();
    }

    /**
     * Ensure component domain services are registered before dispatching AJAX handlers.
     *
     * @return void
     */
    private function bootDomainServices(): void
    {
        $container = Factory::getContainer();

        if ($container->has(EmailService::class) && $container->has(FormRepository::class)) {
            return;
        }

        $basePath = \defined('JPATH_ADMINISTRATOR')
            ? constant('JPATH_ADMINISTRATOR')
            : dirname(__DIR__, 4);
        $providerPath = $basePath . '/components/com_nxpeasyforms/services/provider.php';

        if (!is_file($providerPath)) {
            return;
        }

        $provider = require $providerPath;

        if (is_object($provider) && method_exists($provider, 'register')) {
            $container->registerServiceProvider($provider);
        }
    }

    /**
     * Lazily resolve the AJAX router from the Joomla dependency injection container.
     *
     * @return AjaxRouter
     */
    private function getRouter(): AjaxRouter
    {
        if ($this->router instanceof AjaxRouter) {
            return $this->router;
        }

        $container = Factory::getContainer();

        if (!$container->has(AjaxRouter::class)) {
            $this->bootDomainServices();
        }

        $this->router = $container->get(AjaxRouter::class);

        return $this->router;
    }
}
