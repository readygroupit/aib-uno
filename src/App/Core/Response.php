<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    private int $statusCode = 200;

    /** @var array<string, string> */
    private array $headers = [];

    private string $body = '';

    /** Vero quando un controller ha gia' deciso l'esito (redirect/json) e il dispatch va interrotto. */
    private bool $finished = false;

    public function setStatusCode(int $code): void
    {
        $this->statusCode = $code;
    }

    public function addHeader(string $name, string $value): void
    {
        $this->headers[$name] = $value;
    }

    public function setBody(string $body): void
    {
        $this->body = $body;
    }

    public function redirect(string $url, int $status = 302): void
    {
        $this->statusCode = $status;
        $this->headers['Location'] = $url;
        $this->finished = true;
    }

    public function json(array $data, int $status = 200): void
    {
        $this->statusCode = $status;
        $this->headers['Content-Type'] = 'application/json; charset=utf-8';
        $this->body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->finished = true;
    }

    public function isFinished(): bool
    {
        return $this->finished;
    }

    public function send(): void
    {
        http_response_code($this->statusCode);
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }
        echo $this->body;
    }
}
