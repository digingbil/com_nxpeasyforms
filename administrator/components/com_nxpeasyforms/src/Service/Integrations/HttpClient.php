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
 * @since 1.0.0
 */
final class HttpClient
{
    private Http $http;

    public function __construct(?Http $http = null)
    {
        $this->http = $http ?? HttpFactory::getHttp();
    }


	/**
	 * Sends an HTTP request to the specified URL with the given parameters.
	 *
	 * @param   string  $method   The HTTP method to use (GET, POST, PUT, DELETE)
	 * @param   string  $url      The URL to send the request to
	 * @param   mixed   $body     The request body data. If array, it will be JSON encoded
	 * @param   array   $headers  The HTTP headers to send with the request
	 * @param   int     $timeout  The timeout period in seconds
	 *
	 * @return  Response     The HTTP response object
	 *
	 * @throws  \JsonException  When JSON encoding of body fails
	 * @since   1.0.0
	 */
	public function request(string $method, string $url, mixed $body = '', array $headers = [], int $timeout = 10): Response
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
	 * Sends a POST request to the specified URL with a string or array body.
	 * Proxy method for request()
	 *
	 * @param   string                       $url      The URL to send the request to
	 * @param   string|array<string, mixed>  $body     The request body
	 * @param   array                        $headers  The HTTP headers to send with the request
	 * @param   int                          $timeout  The timeout period in seconds
	 *
	 * @return Response The HTTP response object
	 * @throws \JsonException When JSON encoding of array body fails
	 * @since 1.0.0
	 */
	public function post(string $url, array|string $body, array $headers = [], int $timeout = 10): Response
    {
        return $this->request('POST', $url, $body, $headers, $timeout);
    }

	/**
	 * Sends request with JSON content type and array body.
	 *
	 * @param   string                $url      The URL to send the request to
	 * @param   array<string, mixed>  $body     The request body that will be JSON encoded
	 * @param   string                $method   The HTTP method to use (default: POST)
	 * @param   array                 $headers  Additional HTTP headers to send with the request
	 * @param   int                   $timeout  The timeout period in seconds
	 *
	 * @return  Response              The HTTP response object
	 * @throws  \JsonException       When JSON encoding of body fails
	 * @since   1.0.0
	 */
	public function sendJson(
		string $url,
		array  $body,
		string $method = 'POST',
		array $headers = [],
        int $timeout = 10
    ): Response {
        $headers['Content-Type'] = 'application/json';

        return $this->request($method, $url, $body, $headers, $timeout);
    }

	/**
	 * Sends a form data request with urlencoded content type.
	 *
	 * @param   string                $url      The URL to send the request to
	 * @param   array<string, mixed>  $fields   The form fields to be encoded and sent
	 * @param   string                $method   The HTTP method to use (default: POST)
	 * @param   array                 $headers  Additional HTTP headers to send with the request
	 * @param   int                   $timeout  The timeout period in seconds
	 *
	 * @return  Response              The HTTP response object
	 * @since   1.0.0
	 * @throws  \JsonException
	 */
	public function sendForm(
		string $url,
		array  $fields,
		string $method = 'POST',
        array $headers = [],
        int $timeout = 10
    ): Response {
        $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        $body = http_build_query($fields, '', '&', PHP_QUERY_RFC3986);

        return $this->request($method, $url, $body, $headers, $timeout);
    }
}
