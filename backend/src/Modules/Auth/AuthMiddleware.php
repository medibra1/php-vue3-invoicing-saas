<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Core\Http\JsonErrorResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Decodes the `Authorization: Bearer <token>` header and, on success,
 * attaches the JWT claims to the request as the `authClaims` attribute
 * for downstream middleware/controllers to read (e.g.
 * TenantResolverMiddleware pulls `tenant_id` from it).
 *
 * On any failure (missing header, expired/invalid token) this
 * short-circuits the pipeline with a 401 — it never lets an
 * unauthenticated request reach the controller. Only applied to
 * route groups that need it (never global — /auth/login itself must
 * stay reachable without a token).
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly JwtDecoder $decoder)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $header = $request->getHeaderLine('Authorization');

        if (!str_starts_with($header, 'Bearer ')) {
            return JsonErrorResponse::build(401, 'Missing or malformed Authorization header.');
        }

        try {
            $claims = $this->decoder->decode(substr($header, 7));
        } catch (InvalidTokenException $e) {
            return JsonErrorResponse::build(401, $e->getMessage());
        }

        return $handler->handle($request->withAttribute('authClaims', $claims));
    }
}
