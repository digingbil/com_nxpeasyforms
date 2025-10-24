<?php

declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations;

use Joomla\CMS\Http\Http;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Http\Response;
use Joomla\CMS\Language\Text;

use const JSON_THROW_ON_ERROR;

/**
 * Thin wrapper around Joomla HTTP client for integration requests.
 */
final class HttpClient
{
    private Http $http;

    public function __construct(?Http $http = null)
    {
        $this->http = $http ?? HttpFactory::getHttp();
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>|string $body
     */
    public function post(string $url, $body, array $headers = [], int $timeout = 10): Response
    {
        if (is_array($body)) {
            $body = json_encode($body, JSON_THROW_ON_ERROR);
            $headers['Content-Type'] = $headers['Content-Type'] ?? 'application/json';
        }

        return $this->http->post(
            $url,
            $body,
            $headers,
            $timeout
        );
    }
}
