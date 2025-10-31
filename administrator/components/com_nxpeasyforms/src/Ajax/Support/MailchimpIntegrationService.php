<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Ajax\Support;

use Joomla\CMS\Language\Text;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations\MailchimpListsService;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Repository\FormRepository;
use Joomla\Component\Nxpeasyforms\Administrator\Support\Secrets;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use function is_array;
use function preg_match;
use function trim;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Provides Mailchimp-specific helpers for administrator AJAX flows.
 */
final class MailchimpIntegrationService
{
    /**
     * @var FormRepository
     */
    private FormRepository $forms;

    /**
     * @var MailchimpListsService
     */
    private MailchimpListsService $listsService;

    /**
     * @param FormRepository $forms Repository used to load stored form configuration.
     * @param MailchimpListsService $listsService Service used to query the Mailchimp API.
     */
    public function __construct(FormRepository $forms, MailchimpListsService $listsService)
    {
        $this->forms = $forms;
        $this->listsService = $listsService;
    }

    /**
     * Resolve the effective Mailchimp API key, preferring the provided key before falling back to stored configuration.
     *
     * @param string $apiKey API key supplied by the request (may be empty).
     * @param int $formId Current form identifier for fallback lookups.
     *
     * @return string Decrypted API key or an empty string when unavailable.
     */
    public function resolveApiKey(string $apiKey, int $formId): string
    {
        $apiKey = trim($apiKey);

        if ($apiKey !== '') {
            return $apiKey;
        }

        if ($formId <= 0) {
            return '';
        }

        try {
            $form = $this->forms->find($formId);

            if (!is_array($form)) {
                return '';
            }

            $config = $form['config'] ?? null;

            if (!is_array($config)) {
                return '';
            }

            $options = $config['options'] ?? null;

            if (!is_array($options)) {
                return '';
            }

            $integrations = $options['integrations'] ?? null;

            if (!is_array($integrations)) {
                return '';
            }

            $mailchimp = $integrations['mailchimp'] ?? null;

            if (!is_array($mailchimp)) {
                return '';
            }

            $storedKey = trim((string) ($mailchimp['api_key'] ?? ''));

            if ($storedKey === '') {
                return '';
            }

            $decrypted = Secrets::decrypt($storedKey);

            if ($decrypted !== '') {
                return $decrypted;
            }

            if ($this->looksLikeMailchimpApiKey($storedKey)) {
                return $storedKey;
            }
        } catch (Throwable $exception) {
            return '';
        }

        return '';
    }

    /**
     * Fetch Mailchimp audiences using the provided API key.
     *
     * @param string $apiKey Mailchimp API key to authenticate the request.
     *
     * @return array<int,array<string,mixed>> List response from Mailchimp.
     */
    public function fetchLists(string $apiKey): array
    {
        try {
            return $this->listsService->fetchLists($apiKey);
        } catch (InvalidArgumentException $exception) {
            throw new RuntimeException(Text::_('COM_NXPEASYFORMS_MAILCHIMP_INVALID_API_KEY'), 400, $exception);
        } catch (RuntimeException $exception) {
            $code = $exception->getCode();

            if ($code === 413) {
                throw new RuntimeException(Text::_('COM_NXPEASYFORMS_MAILCHIMP_RESPONSE_TOO_LARGE'), 413, $exception);
            }

            if ($code >= 400 && $code < 600) {
                throw new RuntimeException(Text::sprintf('COM_NXPEASYFORMS_MAILCHIMP_HTTP_ERROR', $code), $code, $exception);
            }

            throw new RuntimeException(Text::_('COM_NXPEASYFORMS_MAILCHIMP_REQUEST_FAILED'), 502, $exception);
        } catch (Throwable $exception) {
            throw new RuntimeException(Text::_('COM_NXPEASYFORMS_MAILCHIMP_REQUEST_FAILED'), 500, $exception);
        }
    }

    /**
     * Quick heuristic to determine whether a value resembles a Mailchimp API key.
     *
     * @param string $value Candidate string to evaluate.
     *
     * @return bool True when the value matches the expected key pattern.
     */
    private function looksLikeMailchimpApiKey(string $value): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9]{10,}-[a-z0-9]+$/', $value);
    }
}
