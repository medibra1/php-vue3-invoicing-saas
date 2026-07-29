<?php

declare(strict_types=1);

namespace App\Modules\Tenant;

/**
 * The tenant resolved for the current request by TenantResolverMiddleware.
 *
 * Bound into the container as an instance (see TenantResolverMiddleware)
 * so any service further down the pipeline — a future base Repository,
 * in particular — can type-hint CurrentTenant and receive it via
 * auto-wiring, instead of threading a tenant id through every method
 * signature by hand. That's the mechanism QueryBuilder::forTenant() was
 * built to be driven by.
 */
final class CurrentTenant
{
    public function __construct(
        public readonly int $id,
        public readonly string $name
    ) {
    }
}
