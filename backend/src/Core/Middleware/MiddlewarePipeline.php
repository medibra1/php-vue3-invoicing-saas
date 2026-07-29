<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Container\Container;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Runs a list of middleware (PSR-15) around a final handler, following
 * the "onion" pattern: each middleware can act both before AND after
 * calling the next one in the chain.
 *
 * Typical chain for a protected route:
 *
 *   CorsMiddleware
 *     -> AuthMiddleware            (decodes the JWT, resolves the user)
 *       -> TenantResolverMiddleware  (resolves the tenant, checks access)
 *         -> PermissionMiddleware      (checks RBAC permissions)
 *           -> [Target controller]
 *
 * Each middleware receives a $next: RequestHandlerInterface representing
 * "the rest of the chain". It can either call $next->handle($request) to
 * continue, or short-circuit by returning a Response directly (e.g. a
 * 401 on an invalid JWT, never reaching the controller).
 */
final class MiddlewarePipeline implements RequestHandlerInterface
{
    /**
     * Each entry is either a plain class-string (built via the
     * container's normal get(), so bind()/singleton() registrations
     * apply) or a `[class-string, mixed $parameter]` tuple for
     * middleware that need per-route configuration — e.g.
     * `[PermissionMiddleware::class, 'invoices.create']`. Tuple
     * middleware are built fresh per route via Container::makeWith(),
     * which injects $parameter into the constructor argument literally
     * named `$parameter` (see PermissionMiddleware).
     *
     * @param array<int, class-string<MiddlewareInterface>|array{0: class-string<MiddlewareInterface>, 1: mixed}> $middlewareClasses
     * @param Container $container Used to instantiate each middleware with
     *                              its own dependencies (e.g. AuthMiddleware
     *                              needs a JwtDecoder).
     * @param \Closure(ServerRequestInterface): ResponseInterface $finalHandler
     *        The chain's terminal handler: dispatches to the controller.
     */
    public function __construct(
        private readonly array $middlewareClasses,
        private readonly Container $container,
        private readonly \Closure $finalHandler,
        private readonly int $index = 0
    ) {
    }

    /**
     * Processes the request through the current middleware in the chain,
     * then delegates to the rest of the chain via a new pipeline instance
     * (index + 1). Once all middleware are exhausted, the final handler
     * (controller dispatch) is called.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->index >= count($this->middlewareClasses)) {
            return ($this->finalHandler)($request);
        }

        $entry = $this->middlewareClasses[$this->index];

        if (is_array($entry)) {
            [$middlewareClass, $parameter] = $entry;
            $middleware = $this->container->makeWith($middlewareClass, ['parameter' => $parameter]);
        } else {
            $middleware = $this->container->get($entry);
        }

        /** @var MiddlewareInterface $middleware */
        $next = new self(
            $this->middlewareClasses,
            $this->container,
            $this->finalHandler,
            $this->index + 1
        );

        return $middleware->process($request, $next);
    }
}
