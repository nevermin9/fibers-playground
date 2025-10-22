<?php
declare(strict_types=1);

namespace App;

class HttpResponse
{
    public readonly array $headers;
    protected string $headersStr;
    protected string $body;

    public function __construct(string $response)
    {
        $parts = explode("\r\n\r\n", $response, 2); 
        
        if (count($parts) < 2) {
            $this->headersStr = $response;
            $this->body = '';
        } else {
            [$h, $b] = $parts;
            $this->headersStr = $h;
            $this->body = $b;
        }

        // ["Accept: */*", "Host: example.com"]
        $h = array_slice(array_map("trim", explode("\r\n", $this->headersStr)), 1);
        $this->headers = array_reduce($h, function ($acc, $current) {
            [$header, $value] = explode(": ", $current);
            $acc[$header] = trim($value);
            return $acc;
        }, []);

        if ($this->isChunked()) {
            $this->body = $this->decodeChunkedBody($this->body);
        }
    }

    protected function isChunked(): bool
    {
        $h = array_find_key($this->headers, fn($v, $k) => strcasecmp($k, 'Transfer-Encoding') === 0);
        if ($h) {
            return strpos($this->headers[$h], 'chunked') !== false;
        }
        return false;
    }
    
    private function decodeChunkedBody(string $body): string
    {
        if (function_exists('http_chunked_decode')) {
             return http_chunked_decode($body);
        }
        
        $decoded = '';
        $i = 0;
        $len = strlen($body);

        while ($i < $len) {
            if (false === $eol = strpos($body, "\r\n", $i)) {
                break;
            }
            
            $chunkLengthHex = trim(substr($body, $i, $eol - $i));
            $chunkLength = hexdec($chunkLengthHex);

            $i = $eol + 2;

            if ($chunkLength === 0) {
                break;
            }
            
            $decoded .= substr($body, $i, $chunkLength);
            
            $i += $chunkLength + 2; 
        }

        return $decoded;
    }

    public function toJSON(): mixed
    {
        return json_decode($this->body);
    }

    public function __toString()
    {
        return implode("\r\n\r\n", [$this->headersStr, $this->body]);
    }
}
