<?php

namespace App\Core;

final class Request
{
    public function uri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        return '/' . ltrim($uri, '/');
    }

    public function path(): string
    {
        $path = parse_url($this->uri(), PHP_URL_PATH);

        return ($path === false || $path === null) ? '/' : $path;
    }

    public function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public function get(string $key, ?string $default = null): ?string
    {
        if (!isset($_GET[$key]) || !is_scalar($_GET[$key])) {
            return $default;
        }

        return (string) $_GET[$key];
    }

    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->get($key);

        return $value === null || $value === '' || !is_numeric($value) ? $default : (int) $value;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    public function hasPost(): bool
    {
        return $_POST !== [];
    }

    public function cookie(string $key, ?string $default = null): ?string
    {
        if (!isset($_COOKIE[$key]) || !is_scalar($_COOKIE[$key])) {
            return $default;
        }

        return (string) $_COOKIE[$key];
    }

    public function server(string $key, string $default = ''): string
    {
        $value = $_SERVER[$key] ?? $default;

        return is_scalar($value) ? (string) $value : $default;
    }

    public function referer(): string
    {
        return $this->server('HTTP_REFERER');
    }
}
