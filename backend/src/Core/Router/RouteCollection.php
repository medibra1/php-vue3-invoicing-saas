<?php

declare(strict_types=1);

namespace App\Core\Router;

/**
 * Simple ordered container for registered routes.
 * Isolated in its own class so a different indexing strategy (by HTTP
 * method, by static prefix...) can be introduced later without changing
 * the Router's public API.
 */
final class RouteCollection
{
    /** @var Route[] */
    private array $routes = [];

    public function add(Route $route): void
    {
        $this->routes[] = $route;
    }

    /** @return Route[] */
    public function all(): array
    {
        return $this->routes;
    }
}
