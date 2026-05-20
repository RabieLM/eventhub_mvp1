<?php

namespace Core;

/**
 * Routeur minimaliste pour le front controller public/index.php.
 */
final class Router
{
    /** @var array<string,array<string,callable|array{0:string,1:string}>> */
    private array $routes = [];

    public function get(string $path, callable|array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function add(string $method, string $path, callable|array $handler): void
    {
        $this->routes[strtoupper($method)][$this->cleanPath($path)] = $handler;
    }

    public function dispatch(string $method, ?string $path = null): void
    {
        $method = strtoupper($method);
        $path = $this->cleanPath($path ?? $this->currentPath());
        $handler = $this->routes[$method][$path] ?? null;

        if ($handler === null && $method === 'HEAD') {
            $handler = $this->routes['GET'][$path] ?? null;
        }

        if ($handler === null) {
            http_response_code(404);
            echo 'Route introuvable.';
            return;
        }

        if (is_array($handler) && is_string($handler[0])) {
            $class = $handler[0];
            $methodName = $handler[1];
            (new $class())->{$methodName}();
            return;
        }

        $handler();
    }

    private function currentPath(): string
    {
        if (isset($_GET['route']) && is_string($_GET['route'])) {
            return $_GET['route'];
        }

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $base = rtrim(str_replace('\\', '/', dirname($script)), '/');

        if (str_contains($path, '/index.php')) {
            $path = substr($path, strpos($path, '/index.php') + strlen('/index.php')) ?: '/';
        } elseif ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base)) ?: '/';
        }

        return $path;
    }

    private function cleanPath(string $path): string
    {
        $path = parse_url($path, PHP_URL_PATH) ?: '/';
        $path = '/' . trim($path, '/');
        return $path === '//' ? '/' : $path;
    }
}
