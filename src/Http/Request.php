<?php

declare(strict_types=1);

namespace App\Http;

use App\Support\Config;

final class Request
{
    private array $query;
    private array $post;
    private array $files;
    private array $server;
    private ?array $json = null;
    private array $routeParams = [];

    public function __construct()
    {
        $this->query = $_GET;
        $this->post = $_POST;
        $this->files = $_FILES;
        $this->server = $_SERVER;
    }

    public function method(): string
    {
        $method = strtoupper((string) ($this->server['REQUEST_METHOD'] ?? 'GET'));

        if ($method === 'POST' && isset($this->post['_method'])) {
            $override = strtoupper((string) $this->post['_method']);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $override;
            }
        }

        return $method;
    }

    public function path(): string
    {
        $uri = (string) ($this->server['REQUEST_URI'] ?? '/');
        $uri = parse_url($uri, PHP_URL_PATH) ?: '/';
        $uri = '/' . trim($uri, '/');

        $basePath = (string) Config::get('app.base_path', '');

        if ($basePath !== '' && ($uri === $basePath || str_starts_with($uri, $basePath . '/'))) {
            $uri = substr($uri, strlen($basePath));
        }

        return $uri === '' ? '/' : $uri;
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST' || in_array($this->method(), ['PUT', 'PATCH', 'DELETE'], true);
    }

    public function wantsJson(): bool
    {
        $accept = (string) ($this->server['HTTP_ACCEPT'] ?? '');
        $requested = (string) ($this->server['HTTP_X_REQUESTED_WITH'] ?? '');

        return str_contains($accept, 'application/json')
            || strtolower($requested) === 'xmlhttprequest';
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->post)) {
            return $this->post[$key];
        }

        return $this->query[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->post);
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);
        return is_scalar($value) ? trim((string) $value) : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->input($key, $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;

        if (!is_array($file) || !isset($file['error'])) {
            return null;
        }

        return $file;
    }

    public function fileList(string $key): array
    {
        $file = $this->files[$key] ?? null;
        if (!is_array($file)) {
            return [];
        }

        if (!is_array($file['error'] ?? null)) {
            return [$file];
        }

        $files = [];
        foreach ($file['error'] as $i => $error) {
            $files[] = [
                'name' => $file['name'][$i],
                'type' => $file['type'][$i],
                'tmp_name' => $file['tmp_name'][$i],
                'error' => $file['error'][$i],
                'size' => $file['size'][$i],
            ];
        }

        return $files;
    }

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function route(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function routeInt(string $key, int $default = 0): int
    {
        $value = $this->routeParams[$key] ?? null;
        return is_numeric($value) ? (int) $value : $default;
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? 'unknown');
    }
}
