<?php
declare(strict_types=1);

namespace App;

class HttpRequest
{
    const TIMEOUT_SEC = 5.0;

    public \Fiber $fiber;
    public $stream;
    public readonly string $host;
    public readonly int $port;
    public readonly string $scheme;
    public readonly string $path;
    public readonly string $headers;
    public readonly string $method;
    public readonly string $startLine;
    public ?string $result = null;
    public RequestState $state = RequestState::Connecting;
    protected float $startTime;
    protected string $writeBuffer;
    protected string $response = "";

    public string $responseAsString {
        get {
            return $this->response;
        }
    }

    public function __construct(string $method, string $url, array $headers)
    {
        $this->method = strtoupper($method);
        $parsed = parse_url($url);
        $this->host = $parsed['host'];
        $this->scheme = $parsed['scheme'];
        $this->port = $parsed['port'] ?? ($this->scheme === 'https' ? 443 : 80);
        $this->path = $parsed['path'] ?? '/';
        $this->startLine = "{$this->method} {$this->path} HTTP/1.1\r\n";
        $headers['Connection'] = 'close';
        $headers['Host'] = $this->host;
        $this->headers = implode(
            "\r\n",
            array_map(
                fn(string $k, string $v) => "{$k}: {$v}",
                array_keys($headers),
                array_values($headers)
            )
        ) . "\r\n\r\n";
        $this->startTime = microtime(true);

        $this->writeBuffer = $this->startLine . $this->headers;
    }

    public function connect(): bool
    {
        $context = null;
        $transport = $this->scheme === 'https' ? 'ssl' : 'tcp';

        if ($transport === 'ssl') {
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false,
                ]
            ]);
        }

        $this->stream = @stream_socket_client(
            "{$transport}://{$this->host}:{$this->port}",
            $errno,
            $errmsg,
            static::TIMEOUT_SEC,
            STREAM_CLIENT_ASYNC_CONNECT | STREAM_CLIENT_CONNECT,
            $context
        );

        if (! $this->stream) {
            $this->result = "Connection failed to {$this->host}:{$this->port} - {$errmsg}";
            $this->state = RequestState::Terminated;
            return false;
        }

        stream_set_blocking($this->stream, false);
        return true;
    }

    public function write(): void
    {
        if ($this->writeBuffer === '') {
            $this->state = RequestState::Reading;
            return;
        }

        $written = fwrite($this->stream, $this->writeBuffer);

        if ($written === false) {
            $this->terminate("Write error");
            return;
        }

        $this->writeBuffer = substr($this->writeBuffer, $written);

        if ($this->writeBuffer === '') {
            $this->state = RequestState::Reading;
        }
    }

    public function read(): void
    {
        $chunk = fread($this->stream, 4096);
        if ($chunk === false) {
            $this->terminate("Read error");
            return;
        }

        $this->response .= $chunk;

        if (feof($this->stream)) {
            $this->close();
        }
    }

    protected function close(): void
    {
        fclose($this->stream);
        $this->stream = null;
        $this->state = RequestState::Terminated;
    }

    public function checkTimout(): void
    {
        if (microtime(true) > ($this->startTime + static::TIMEOUT_SEC)) {
            $this->terminate("Timeout after " . static::TIMEOUT_SEC . "s");
        }
    }

    public function terminate(string $msg): void
    {
        $this->result = $msg;
        $this->state = RequestState::Terminated;
        if ($this->stream) {
            fclose($this->stream);
            $this->stream = null;
        }
    }
}
