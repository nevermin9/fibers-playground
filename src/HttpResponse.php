<?php
declare(strict_types=1);

namespace App;

class HttpResponse
{
    public readonly string $body;
    public readonly string $headers;

    public function __construct(public readonly string $response)
    {
        [$h, $b] = explode("\r\n\r\n", $this->response);
        $this->headers = $h;
        $this->body = $b;
    }

    public function toJSON()
    {
        return json_decode($this->body);
    }

    public function __toString()
    {
        return $this->response;
    }
}
