<?php

declare(strict_types=1);

namespace App;

use App\Http\Request;

final class Router
{
    private array $routes = [];

    public function get(string $path, array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function add(string $method, string $path, array $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'regex' => $this->compile($path),
            'handler' => $handler,
        ];
    }

    private function compile(string $path): string
    {
        $pattern = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#',
            static fn (array $m): string => '(?P<' . $m[1] . '>[^/]+)',
            $path
        );

        return '#^' . $pattern . '$#';
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path = $request->path();
        $methodMatched = false;

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }

            $methodMatched = true;

            if ($route['method'] !== $method) {
                continue;
            }

            $params = array_filter(
                $matches,
                static fn (string $key): bool => !is_int($key),
                ARRAY_FILTER_USE_KEY
            );

            $request->setRouteParams($params);
            $this->invoke($route['handler'], $request);
            return;
        }

        if ($methodMatched) {
            abort(405, 'Method not allowed for this route.');
        }

        abort(404, 'No route matched ' . $method . ' ' . $path);
    }

    private function invoke(array $handler, Request $request): void
    {
        [$class, $method] = $handler;
        $controller = new $class();

        if (!method_exists($controller, $method)) {
            throw new \RuntimeException("Controller method {$class}::{$method} not found.");
        }

        $controller->{$method}($request);
    }
}
