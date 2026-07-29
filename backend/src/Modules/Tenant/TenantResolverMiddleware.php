<?php

declare(strict_types=1);

namespace App\Modules\Tenant;

use App\Core\Container\Container;
use App\Core\Http\JsonErrorResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Reads `tenant_id` from the JWT claims AuthMiddleware attached to the
 * request, then *verifies* it against the database rather than trusting
 * the claim blindly — a tenant deactivated/soft-deleted after the token
 * was issued is rejected immediately instead of staying valid until the
 * token expires. Must run after AuthMiddleware in the pipeline (see
 * MiddlewarePipeline's class-level doc for the standard chain order).
 *
 * On success, binds the resolved CurrentTenant into the container (so
 * downstream services can auto-wire it — see CurrentTenant's doc) and
 * attaches it to the request as the `tenant` attribute.
 */
final class TenantResolverMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly TenantRepository $tenants,
        private readonly Container $container
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $claims = $request->getAttribute('authClaims');
        $tenantId = is_array($claims) ? ($claims['tenant_id'] ?? null) : null;

        if ($tenantId === null) {
            return JsonErrorResponse::build(
                401,
                'Missing tenant context — is AuthMiddleware applied before TenantResolverMiddleware?'
            );
        }

        $row = $this->tenants->findActiveById((int) $tenantId);

        if ($row === null) {
            return JsonErrorResponse::build(403, 'Tenant not found or inactive.');
        }

        $tenant = new CurrentTenant((int) $row['id'], (string) $row['name']);

        $this->container->instance(CurrentTenant::class, $tenant);

        return $handler->handle($request->withAttribute('tenant', $tenant));
    }
}
