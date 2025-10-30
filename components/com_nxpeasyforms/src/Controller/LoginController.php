<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Site\Controller;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Response\JsonResponse;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Authentication\UserLoginHandler;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Repository\FormRepository;
use Joomla\Database\DatabaseDriver;

\defined('_JEXEC') or die;

/**
 * Site-based controller for handling user login submissions.
 * This controller runs in the 'site' application context, allowing it to
 * establish a user session upon successful login.
 */
final class LoginController extends BaseController
{
    /**
     * Constructor. Note that Joomla's MVCFactory passes the MVCFactory itself
     * as the second parameter, not the application. We override to handle this properly.
     *
     * @param array $config Optional configuration array
     * @param CMSApplicationInterface|MVCFactoryInterface|null $app The application or factory (MVCFactory passes itself)
     * @param \Joomla\Input\Input|null $input Optional input object
     */
    public function __construct($config = [], $app = null, $input = null)
    {
        // When called from MVCFactory, $app is actually the MVCFactory instance, not CMSApplicationInterface
        // So we need to get the application properly from Factory
        $actualApp = null;
        if ($app instanceof CMSApplicationInterface) {
            $actualApp = $app;
        }

        parent::__construct($config, $actualApp, $input);
    }

    /**
     * Handles the login form submission.
     */
    public function submit(): void
    {
        $app = Factory::getApplication();
        $input = $app->input;

        $app->setHeader('Content-Type', 'application/json');

        try {
            $formId = $input->getInt('formId', 0);
            $data = $input->post->getArray();

            if ($formId <= 0) {
                throw new \RuntimeException('Invalid form ID.', 400);
            }

            // Ensure the component is booted so domain services are registered
            try {
                if (method_exists($app, 'bootComponent')) {
                    $app->bootComponent('com_nxpeasyforms');
                }
            } catch (\Throwable $bootException) {
                // Ignore boot errors here; we will fall back to manual instantiation
            }

            $container = Factory::getContainer();

            // We need the form to get the login integration settings
            /** @var FormRepository $formRepository */
            if (method_exists($container, 'has') && $container->has(FormRepository::class)) {
                $formRepository = $container->get(FormRepository::class);
            } else {
                /** @var DatabaseDriver $db */
                $db = $container->get(DatabaseDriver::class);
                $formRepository = new FormRepository($db);
            }
            $form = $formRepository->find($formId);

            if (!$form) {
                throw new \RuntimeException('Form not found.', 404);
            }

            $loginConfig = $form['config']['options']['integrations']['user_login'] ?? [];

            if (empty($loginConfig['enabled'])) {
                throw new \RuntimeException('Login is not enabled for this form.', 403);
            }

            // We don't use the full SubmissionService here to avoid all the other machinery
            // (emails, other integrations, etc.). We only care about logging the user in.
            /** @var UserLoginHandler $loginHandler */
            if (method_exists($container, 'has') && $container->has(UserLoginHandler::class)) {
                $loginHandler = $container->get(UserLoginHandler::class);
            } else {
                /** @var DatabaseDriver $db */
                $db = $container->get(DatabaseDriver::class);
                $loginHandler = new UserLoginHandler(null, $db);
            }
            $result = $loginHandler->login($data, $loginConfig);

            if (!$result['success']) {
                // Use a generic message to avoid leaking information
                throw new \RuntimeException('Login failed. Please check your credentials and try again.', 401);
            }

            // The UserLoginHandler already established the session.
            // We just need to return a success response.
            $responsePayload = [
                'success' => true,
                'message' => $result['message'],
                'data' => [], // No need to return submitted data
            ];

            if (!empty($result['redirect'])) {
                $responsePayload['redirect'] = $result['redirect'];
            }

            echo new JsonResponse($responsePayload);

        } catch (\Throwable $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            echo new JsonResponse(['success' => false, 'message' => $e->getMessage()], $statusCode);
        }

        $app->close();
    }
}
