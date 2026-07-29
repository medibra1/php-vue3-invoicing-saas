<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Container\Container;
use App\Core\Http\JsonErrorResponse;
use App\Core\Http\ResponseEmitter;
use App\Core\Middleware\MiddlewarePipeline;
use App\Core\Router\MethodNotAllowedException;
use App\Core\Router\RouteNotFoundException;
use App\Core\Router\Router;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Application entry point.
 *
 * Wires the Router, the DI Container and the MiddlewarePipeline together,
 * and is the single place that turns anything that can go wrong (no
 * matching route, wrong HTTP method, uncaught exception in a middleware
 * or controller) into a clean JSON response instead of a raw PHP error
 * page or a blank one.
 *
 * public/index.php only builds the Container/Router and calls run();
 * every other line of the HTTP lifecycle lives here.
 */
final class Kernel
{
    private readonly Psr17Factory $psr17Factory;

    /**
     * @param array<int, class-string> $globalMiddlewares Run on every
     *        request, before any route-specific middleware (e.g. Cors,
     *        JsonBodyParser, RateLimit — see CLAUDE.md pipeline order).
     */
    public function __construct(
        private readonly Router $router,
        private readonly Container $container,
        private readonly array $globalMiddlewares = []
    ) {
        $this->psr17Factory = new Psr17Factory();
    }

    /**
     * Builds the request from PHP's superglobals, dispatches it, and
     * emits the response to the client. The only method public/index.php
     * needs to call.
     */
    public function run(): void
    {
        $creator = new ServerRequestCreator(
            $this->psr17Factory,
            $this->psr17Factory,
            $this->psr17Factory,
            $this->psr17Factory
        );

        $response = $this->handle($creator->fromGlobals());

        (new ResponseEmitter())->emit($response);
    }

    /**
     * Runs the routing + middleware + controller pipeline for a given
     * request. Never throws: every failure mode (404, 405, uncaught
     * exception) is converted into a JSON error response, which also
     * keeps this method safe to call directly from tests without a real
     * HTTP environment.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            ['route' => $route, 'params' => $params] = $this->router->match($request);
        } catch (RouteNotFoundException $e) {
            return $this->jsonError(404, 'Not Found', $e->getMessage());
        } catch (MethodNotAllowedException $e) {
            $allowed = array_values(array_unique($e->allowedMethods));

            return $this->jsonError(405, 'Method Not Allowed', $e->getMessage(), ['allowed' => $allowed])
                ->withHeader('Allow', implode(', ', $allowed));
        }

        $finalHandler = function (ServerRequestInterface $request) use ($route, $params): ResponseInterface {
            // Bound per-request so controllers/middleware can type-hint
            // ServerRequestInterface and receive it via auto-wiring.
            $this->container->instance(ServerRequestInterface::class, $request);

            return $this->container->call($route->handler, $params);
        };

        $pipeline = new MiddlewarePipeline(
            array_merge($this->globalMiddlewares, $route->middlewares),
            $this->container,
            $finalHandler
        );

        try {
            return $pipeline->handle($request);
        } catch (Throwable $e) {
            return $this->jsonError(500, 'Internal Server Error', $e->getMessage());
        }
    }

    /**
     * The raw exception message is only exposed when APP_DEBUG=true, so
     * a production deployment never leaks internals (SQL, file paths,
     * stack traces) to the client.
     *
     * @param array<string, mixed> $extra Always-safe extra fields (e.g. allowed methods on a 405)
     */
    private function jsonError(int $status, string $message, string $detail, array $extra = []): ResponseInterface
    {
        if (filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN)) {
            $extra['detail'] = $detail;
        }

        return JsonErrorResponse::build($status, $message, $extra);
    }
}
