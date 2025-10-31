<?php

declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Tests\Integrations;

use InvalidArgumentException;
use Joomla\CMS\Http\Http;
use Joomla\CMS\Http\Response;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations\HttpClient;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Integrations\MailchimpListsService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use const JSON_THROW_ON_ERROR;

final class MailchimpListsServiceTest extends TestCase
{
    public function testFetchListsReturnsSanitisedAudiences(): void
    {
        $payload = json_encode(
            [
                'lists' => [
                    ['id' => 'abc123', 'name' => '  Newsletter Subscribers  '],
                    ['id' => '', 'name' => 'Missing'],
                ],
            ],
            JSON_THROW_ON_ERROR
        );

        $response = new Response(200, $payload ?: '{}');
        $http = new RecordingHttp($response);
        $service = new MailchimpListsService(new HttpClient($http));

        $lists = $service->fetchLists('abcd1234-us1');

        $this->assertSame([
            ['id' => 'abc123', 'name' => 'Newsletter Subscribers'],
        ], $lists);

        $this->assertSame(
            'https://us1.api.mailchimp.com/3.0/lists?fields=lists.id,lists.name,total_items&count=100',
            $http->lastUrl
        );

        $this->assertArrayHasKey('Authorization', $http->lastHeaders);
        $this->assertSame(
            'Basic ' . base64_encode('nxp:abcd1234-us1'),
            $http->lastHeaders['Authorization']
        );
    }

    public function testFetchListsThrowsWhenApiKeyInvalid(): void
    {
        $service = new MailchimpListsService(new HttpClient(new RecordingHttp(new Response())));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Mailchimp API key is invalid.');

        $service->fetchLists('invalid-key');
    }

    public function testFetchListsThrowsOnHttpError(): void
    {
        $http = new RecordingHttp(new Response(401, '{"detail":"Invalid"}'));
        $service = new MailchimpListsService(new HttpClient($http));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Mailchimp API responded with an error status.');
        $this->expectExceptionCode(401);

        $service->fetchLists('abcd1234-us2');
    }
}

final class RecordingHttp extends Http
{
    public string $lastUrl = '';

    /**
     * @var array<string,string>
     */
    public array $lastHeaders = [];

    private Response $response;

    public function __construct(Response $response)
    {
        $this->response = $response;
    }

    public function get(string $url, array $headers = [], int $timeout = 10): Response
    {
        $this->lastUrl = $url;
        $this->lastHeaders = $headers;

        return $this->response;
    }
}
