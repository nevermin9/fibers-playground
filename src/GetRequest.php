<?php
declare(strict_types=1);

namespace App;

class GetRequest extends HttpRequest
{
    public function __construct(string $url, array $headers)
    {
        parent::__construct("GET", $url, $headers);
    }
}
