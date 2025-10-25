<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations;

use Joomla\CMS\Http\Http;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Http\Response;
use function http_build_query;
use function is_array;
use function json_encode;
use function method_exists;
use function strtoupper;


use const PHP_QUERY_RFC3986;
use const JSON_THROW_ON_ERROR;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

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
    public function request(string $method, string $url, $body = '', array $headers = [], int $timeout = 10): Response
    {
        $method = strtoupper($method);

        if (is_array($body)) {
            $headers['Content-Type'] = $headers['Content-Type'] ?? 'application/json';
            $body = json_encode($body, JSON_THROW_ON_ERROR);
        }

        switch ($method) {
            case 'PUT':
                if (method_exists($this->http, 'put')) {
                    return $this->http->put($url, $body, $headers, $timeout);
                }
                break;
            case 'GET':
                if (method_exists($this->http, 'get')) {
                    return $this->http->get($url, $headers, $timeout);
                }
                break;
            case 'DELETE':
                if (method_exists($this->http, 'delete')) {
                    return $this->http->delete($url, $headers, $timeout);
                }
                break;
            default:
                break;
        }

        return $this->http->post($url, $body, $headers, $timeout);
    }

    /**
     * @param array<string, mixed>|string $body
     */
    public function post(string $url, $body, array $headers = [], int $timeout = 10): Response
    {
        return $this->request('POST', $url, $body, $headers, $timeout);
    }

    /**
     * @param array<string, mixed> $body
     */
    public function sendJson(
        string $url,
        array $body,
        string $method = 'POST',
        array $headers = [],
        int $timeout = 10
    ): Response {
        $headers['Content-Type'] = 'application/json';

        return $this->request($method, $url, $body, $headers, $timeout);
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function sendForm(
        string $url,
        array $fields,
        string $method = 'POST',
        array $headers = [],
        int $timeout = 10
    ): Response {
        $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        $body = http_build_query($fields, '', '&', PHP_QUERY_RFC3986);

        return $this->request($method, $url, $body, $headers, $timeout);
    }
}
