<?php

declare(strict_types=1);

namespace App\Core;

class Router
{
    private array $routes = [];

    public function get(string $path, array $handler, bool $auth = false, bool $csrf = true): void
    {
        $this->add('GET', $path, $handler, $auth, $csrf);
    }

    public function post(string $path, array $handler, bool $auth = false, bool $csrf = true): void
    {
        $this->add('POST', $path, $handler, $auth, $csrf);
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $method = strtoupper($_POST['_method'] ?? $method);

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $params = $this->match($route['path'], $path);

            if ($params === null) {
                continue;
            }

            if ($route['auth'] && ! current_user()) {
                redirect('/login');
            }

            if ($method !== 'GET' && $route['csrf'] && ! csrf_valid($_POST['_token'] ?? '')) {
                flash('error', 'Your session expired. Please try again.');
                back();
            }

            [$class, $action] = $route['handler'];
            $controller = new $class();
            $controller->{$action}(...array_values($params));

            return;
        }

        http_response_code(404);
        echo '404 Not Found';
    }

    private function add(string $method, string $path, array $handler, bool $auth, bool $csrf): void
    {
        $this->routes[] = compact('method', 'path', 'handler', 'auth', 'csrf');
    }

    private function match(string $routePath, string $requestPath): ?array
    {
        $pattern = preg_replace('#\{([A-Za-z_][A-Za-z0-9_]*)}#', '(?P<$1>[^/]+)', $routePath);
        $pattern = '#^'.($pattern ?: '').'$#';

        if (! preg_match($pattern, $requestPath, $matches)) {
            return null;
        }

        return array_filter(
            $matches,
            static fn ($key) => is_string($key),
            ARRAY_FILTER_USE_KEY,
        );
    }
}
