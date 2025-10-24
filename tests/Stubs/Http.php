<?php

declare(strict_types=1);

namespace Joomla\CMS\Http;

class Response
{
    public int $code;

    public string $body;

    public function __construct(int $code = 200, string $body = '{"success":true}')
    {
        $this->code = $code;
        $this->body = $body;
    }
}

class Http
{
    public function post(string $url, $body = '', array $headers = [], int $timeout = 10): Response
    {
        return new Response(200, '{"success":true,"score":1.0}');
    }
}

class HttpFactory
{
    public static function getHttp(): Http
    {
        return new Http();
    }
}
