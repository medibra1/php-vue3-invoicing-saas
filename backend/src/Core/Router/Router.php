<?php

declare(strict_types=1);

namespace App\Core\Router;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Homemade HTTP router.
 *
 * Features:
 * - Registration of GET/POST/PUT/DELETE routes with dynamic parameters ({id}).
 * - Route groups sharing a URI prefix and common middleware (e.g. every
 *   /api/v1/invoices/* route goes through AuthMiddleware +
 *   TenantResolverMiddleware).
 * - Explicit distinction between 404 (no URI matches) and 405 (the URI
 *   exists but not with this HTTP method), following standard HTTP
 *   semantics.
 *
 * Matching is done by iterating over routes in registration order
 * (first match wins), which is plenty for a route count in the low
 * hundreds; beyond that, splitting by method+prefix into an associative
 * array would be preferable to avoid a linear scan.
 */
final class Router
{
    private RouteCollection $routes;

    /** Prefix accumulated during a nested group() */
    private string $groupPrefix = '';

    /** @var array<class-string> Middleware accumulated during a nested group() */
    private array $groupMiddlewares = [];

    public function __construct()
    {
        $this->routes = new RouteCollection();
    }

    /** @param array{0: class-string, 1: string} $handler */
    public function get(string $uri, array $handler, array $middlewares = []): void
    {
        $this->addRoute('GET', $uri, $handler, $middlewares);
    }

    /** @param array{0: class-string, 1: string} $handler */
    public function post(string $uri, array $handler, array $middlewares = []): void
    {
        $this->addRoute('POST', $uri, $handler, $middlewares);
    }

    /** @param array{0: class-string, 1: string} $handler */
    public function put(string $uri, array $handler, array $middlewares = []): void
    {
        $this->addRoute('PUT', $uri, $handler, $middlewares);
    }

    /** @param array{0: class-string, 1: string} $handler */
    public function delete(string $uri, array $handler, array $middlewares = []): void
    {
        $this->addRoute('DELETE', $uri, $handler, $middlewares);
    }

    /**
     * Groups routes under a shared URI prefix and common middleware.
     *
     * Example:
     * ```php
     * $router->group(['prefix' => '/api/v1/invoices', 'middleware' => [AuthMiddleware::class]],
     *     function (Router $r) {
     *         $r->get('', [InvoiceController::class, 'index']);
     *         $r->get('/{id}', [InvoiceController::class, 'show']);
     *     }
     * );
     * ```
     * Groups can be nested: prefixes and middleware accumulate, then are
     * restored to their previous state when leaving the group() call
     * (an implicit stack via local save/restore).
     *
     * @param array{prefix?: string, middleware?: array<class-string>} $attributes
     */
    public function group(array $attributes, callable $callback): void
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddlewares = $this->groupMiddlewares;

        $this->groupPrefix .= $attributes['prefix'] ?? '';
        $this->groupMiddlewares = array_merge(
            $this->groupMiddlewares,
            $attributes['middleware'] ?? []
        );

        $callback($this);

        $this->groupPrefix = $previousPrefix;
        $this->groupMiddlewares = $previousMiddlewares;
    }

    /** @param array{0: class-string, 1: string} $handler */
    private function addRoute(string $method, string $uri, array $handler, array $middlewares): void
    {
        $fullUri = rtrim($this->groupPrefix . $uri, '/') ?: '/';
        $allMiddlewares = array_merge($this->groupMiddlewares, $middlewares);

        $this->routes->add(new Route($method, $fullUri, $handler, $allMiddlewares));
    }

    /**
     * Finds the route matching the incoming request.
     *
     * @return array{route: Route, params: array<string, string>}
     * @throws RouteNotFoundException      no registered URI matches
     * @throws MethodNotAllowedException   the URI exists but not with this method
     */
    public function match(ServerRequestInterface $request): array
    {
        $method = $request->getMethod();
        $uri = rtrim($request->getUri()->getPath(), '/') ?: '/';

        /** @var string[] $allowedMethods */
        $allowedMethods = [];

        foreach ($this->routes->all() as $route) {
            $params = $route->matches($uri);

            if ($params === false) {
                continue;
            }

            if ($route->method !== $method) {
                // The URI matches but not the method: keep track of it
                // so we can return an explicit 405 instead of a
                // misleading 404.
                $allowedMethods[] = $route->method;
                continue;
            }

            return ['route' => $route, 'params' => $params];
        }

        if (!empty($allowedMethods)) {
            throw new MethodNotAllowedException($allowedMethods);
        }

        throw new RouteNotFoundException("No route matches [{$method} {$uri}].");
    }
}
