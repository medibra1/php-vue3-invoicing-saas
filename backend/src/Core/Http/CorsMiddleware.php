<?php

declare(strict_types=1);

namespace App\Core\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Origin allow-list, not a wildcard: the API is called with credentials
 * (the JWT, via the Authorization header — cookies aren't used, but the
 * same-origin-list discipline still applies), and `Access-Control-Allow-
 * Origin: *` combined with allowing credentials is both invalid per spec
 * and a real leak vector, so each request's Origin is checked against an
 * explicit list rather than reflected/wildcarded blindly.
 *
 * Must run before routing (global middleware, first in the chain) so a
 * preflight OPTIONS request to a path with no matching route still gets
 * a correct CORS response instead of a 404 the browser can't use.
 */
final class CorsMiddleware implements MiddlewareInterface
{
    /**
     * @param string[] $allowedOrigins
     * @param string[] $allowedMethods
     * @param string[] $allowedHeaders
     */
    public function __construct(
        private readonly array $allowedOrigins,
        private readonly array $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        private readonly array $allowedHeaders = ['Content-Type', 'Authorization']
    ) {
    }

    public static function fromEnv(): self
    {
        $origins = array_values(array_filter(array_map(
            'trim',
            explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173'))
        )));

        return new self($origins);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $allowOrigin = $this->resolveAllowedOrigin($request->getHeaderLine('Origin'));

        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            return $this->withCorsHeaders((new Psr17Factory())->createResponse(204), $allowOrigin);
        }

        return $this->withCorsHeaders($handler->handle($request), $allowOrigin);
    }

    private function resolveAllowedOrigin(string $origin): ?string
    {
        return $origin !== '' && in_array($origin, $this->allowedOrigins, true) ? $origin : null;
    }

    private function withCorsHeaders(ResponseInterface $response, ?string $allowOrigin): ResponseInterface
    {
        if ($allowOrigin === null) {
            return $response;
        }

        return $response
            ->withHeader('Access-Control-Allow-Origin', $allowOrigin)
            ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
            ->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders))
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Vary', 'Origin');
    }
}
