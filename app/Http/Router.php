<?php

declare(strict_types=1);

namespace SanadAi\Http;

use RuntimeException;

final class Router
{
    /**
     * @var array<string, array<int, array{
     *     path: string,
     *     handler: callable,
     *     middleware: array<int, callable>
     * }>>
     */
    private array $routes = [];

    public function get(
        string $path,
        callable $handler,
        array $middleware = []
    ): void {
        $this->add(
            'GET',
            $path,
            $handler,
            $middleware
        );
    }

    public function post(
        string $path,
        callable $handler,
        array $middleware = []
    ): void {
        $this->add(
            'POST',
            $path,
            $handler,
            $middleware
        );
    }

    public function put(
        string $path,
        callable $handler,
        array $middleware = []
    ): void {
        $this->add(
            'PUT',
            $path,
            $handler,
            $middleware
        );
    }

    public function delete(
        string $path,
        callable $handler,
        array $middleware = []
    ): void {
        $this->add(
            'DELETE',
            $path,
            $handler,
            $middleware
        );
    }

    /**
     * Dispatch the current request.
     *
     * Middleware is executed only when the route
     * actually matches the request.
     */
    public function dispatch(
        string $method,
        string $path
    ): mixed {
        $method = strtoupper(trim($method));

        if ($method === '') {
            throw new RuntimeException(
                'HTTP method cannot be empty.'
            );
        }

        $path = $this->normalizePath($path);

        foreach ($this->routes[$method] ?? [] as $route) {
            $parameters = $this->match(
                $route['path'],
                $path
            );

            if ($parameters === null) {
                continue;
            }

            /*
             * Execute middleware in registration order.
             *
             * Important:
             * middleware must be registered as callables.
             *
             * Example:
             *
             * static function () use ($sessionMiddleware): void {
             *     $sessionMiddleware->authenticate();
             * }
             */
            foreach ($route['middleware'] as $middleware) {
                $middleware();
            }

            /*
             * Execute the route handler only after
             * every middleware has successfully completed.
             */
            return ($route['handler'])(
                ...array_values($parameters)
            );
        }

        throw new NotFoundException(
            'Route not found.'
        );
    }

    /**
     * Register a route.
     *
     * @param array<int, callable> $middleware
     */
    private function add(
        string $method,
        string $path,
        callable $handler,
        array $middleware = []
    ): void {
        $method = strtoupper(trim($method));

        if ($method === '') {
            throw new RuntimeException(
                'HTTP method cannot be empty.'
            );
        }

        $path = $this->normalizePath($path);

        if ($path === '') {
            throw new RuntimeException(
                'Route path cannot be empty.'
            );
        }

        foreach ($middleware as $item) {
            if (!is_callable($item)) {
                throw new RuntimeException(
                    'Invalid route middleware.'
                );
            }
        }

        $this->routes[$method][] = [
            'path' => $path,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    /**
     * Match a registered route against the requested path.
     *
     * Supported syntax:
     *
     * /api/clients/{publicId}
     * /api/services/{publicId}
     * /api/transactions/{publicId}
     *
     * @return array<string, string>|null
     */
    private function match(
        string $routePath,
        string $requestPath
    ): ?array {
        $routeSegments = $this->segments(
            $routePath
        );

        $requestSegments = $this->segments(
            $requestPath
        );

        if (
            count($routeSegments)
            !== count($requestSegments)
        ) {
            return null;
        }

        $parameters = [];

        foreach (
            $routeSegments
            as $index => $routeSegment
        ) {
            $requestSegment =
                $requestSegments[$index];

            /*
             * Dynamic parameter:
             *
             * {publicId}
             */
            if (
                strlen($routeSegment) >= 3
                && $routeSegment[0] === '{'
                && substr($routeSegment, -1) === '}'
            ) {
                $parameterName = substr(
                    $routeSegment,
                    1,
                    -1
                );

                if (
                    $parameterName === ''
                    || !preg_match(
                        '/^[A-Za-z_][A-Za-z0-9_]*$/',
                        $parameterName
                    )
                ) {
                    throw new RuntimeException(
                        'Invalid route parameter.'
                    );
                }

                $parameters[$parameterName] =
                    rawurldecode(
                        $requestSegment
                    );

                continue;
            }

            /*
             * Static segment.
             */
            if (
                $routeSegment
                !== $requestSegment
            ) {
                return null;
            }
        }

        return $parameters;
    }

    /**
     * Split a path into segments.
     *
     * @return array<int, string>
     */
    private function segments(
        string $path
    ): array {
        $path = $this->normalizePath($path);

        if ($path === '') {
            return [];
        }

        return explode(
            '/',
            trim($path, '/')
        );
    }

    /**
     * Normalize route/request paths.
     *
     * Examples:
     *
     * /api/services
     * /api/services/
     *
     * are treated as the same path.
     */
    private function normalizePath(
        string $path
    ): string {
        $path = trim($path);

        if ($path === '') {
            return '';
        }

        /*
         * Remove query string if a full URL-style path
         * accidentally reaches the router.
         */
        $queryPosition = strpos(
            $path,
            '?'
        );

        if ($queryPosition !== false) {
            $path = substr(
                $path,
                0,
                $queryPosition
            );
        }

        $path = trim(
            $path,
            '/'
        );

        if ($path === '') {
            return '/';
        }

        return '/' . $path;
    }
}
