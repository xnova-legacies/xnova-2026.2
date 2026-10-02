<?php

namespace App\Core;

final class Response
{
    private array $headers = [];

    private function __construct(
        private string $content = '',
        private int $status = 200,
    ) {
    }

    public static function html(string $content, int $status = 200): self
    {
        return (new self($content, $status))->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    public static function raw(string $content, string $contentType = 'text/html; charset=UTF-8', int $status = 200): self
    {
        return (new self($content, $status))->withHeader('Content-Type', $contentType);
    }

    public static function json(array $payload, int $status = 200): self
    {
        $content = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return (new self($content === false ? '' : $content, $status))
            ->withHeader('Content-Type', 'application/json; charset=UTF-8');
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return (new self('', $status))->withHeader('Location', $location);
    }

    public static function notFound(string $message = '404 Not Found'): self
    {
        return self::raw($message . "\n", 'text/plain; charset=UTF-8', 404);
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function content(): string
    {
        return $this->content;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);

            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }

        echo $this->content;
    }
}
