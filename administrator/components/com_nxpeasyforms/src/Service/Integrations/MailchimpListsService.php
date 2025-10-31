<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations;

use InvalidArgumentException;
use Joomla\Component\Nxpeasyforms\Administrator\Support\Sanitizer;
use RuntimeException;

use function base64_encode;
use function count;
use function explode;
use function is_array;
use function is_string;
use function json_decode;
use function preg_replace;
use function sprintf;
use function strlen;
use function strtolower;
use function trim;

use const JSON_THROW_ON_ERROR;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Provides Mailchimp audience fetching capabilities for the administrator builder.
 *
 * @since 1.0.0
 */
final class MailchimpListsService
{
    private const RESPONSE_LIMIT = 262144; // 256 KB safety ceiling

    private HttpClient $client;

    public function __construct(?HttpClient $client = null)
    {
        $this->client = $client ?? new HttpClient();
    }

    /**
     * Fetch the first 100 Mailchimp audiences available to the account identified by the API key.
     *
     * @param string $apiKey Mailchimp API key (either freshly provided or decrypted from storage).
     *
     * @return array<int, array<string, string>> Simplified audience records keyed by id and name.
     *
     * @throws InvalidArgumentException When the API key is empty or malformed.
     * @throws RuntimeException If the Mailchimp API request fails or returns invalid data.
     *
     * @since 1.0.0
     */
    public function fetchLists(string $apiKey): array
    {
        $apiKey = trim($apiKey);

        if ($apiKey === '') {
            throw new InvalidArgumentException('Mailchimp API key is required.');
        }

        $dataCenter = $this->extractDatacenter($apiKey);

        if ($dataCenter === '') {
            throw new InvalidArgumentException('Mailchimp API key is invalid.');
        }

        $endpoint = sprintf(
            'https://%s.api.mailchimp.com/3.0/lists?fields=lists.id,lists.name,total_items&count=100',
            $dataCenter
        );

        $headers = [
            'Authorization' => 'Basic ' . base64_encode('nxp:' . $apiKey),
        ];

        try {
            $response = $this->client->request('GET', $endpoint, '', $headers, 10);
        } catch (\Throwable $exception) {
            throw new RuntimeException('Unable to contact Mailchimp API.', 0, $exception);
        }

        $status = (int) ($response->code ?? 0);

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('Mailchimp API responded with an error status.', $status);
        }

        $body = (string) ($response->body ?? '');

        if ($body === '') {
            return [];
        }

        if (strlen($body) > self::RESPONSE_LIMIT) {
            throw new RuntimeException('Mailchimp API response exceeded size limits.', 413);
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('Mailchimp API returned invalid JSON.', 0, $exception);
        }

        $lists = [];

        if (isset($decoded['lists']) && is_array($decoded['lists'])) {
            foreach ($decoded['lists'] as $list) {
                if (!is_array($list)) {
                    continue;
                }

                $id = isset($list['id']) ? trim((string) $list['id']) : '';
                $name = isset($list['name']) ? Sanitizer::cleanText($list['name']) : '';

                if ($id === '' || $name === '') {
                    continue;
                }

                $lists[] = [
                    'id' => $id,
                    'name' => $name,
                ];
            }
        }

        return $lists;
    }

    /**
     * Extract the datacenter for a Mailchimp API key (format: key-datacenter).
     *
     * @param string $apiKey The API key provided by the user.
     *
     * @return string Sanitised datacenter identifier or empty string when invalid.
     *
     * @since 1.0.0
     */
    private function extractDatacenter(string $apiKey): string
    {
        $parts = explode('-', $apiKey);

        if (count($parts) < 2) {
            return '';
        }

        $candidate = strtolower(trim((string) end($parts)));
        $clean = preg_replace('/[^a-z0-9]/', '', $candidate);

        return is_string($clean) ? $clean : '';
    }
}
