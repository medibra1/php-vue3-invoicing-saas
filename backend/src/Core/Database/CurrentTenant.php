<?php

declare(strict_types=1);

namespace App\Core\Database;

/**
 * The tenant resolved for the current request. Lives in Core (not
 * Modules/Tenant, where it originally sat) so the base Repository class
 * in this same namespace can type-hint it without Core depending on a
 * Modules class — Core stays framework-level and knows nothing about
 * any specific module.
 *
 * Modules\Tenant\TenantResolverMiddleware is what actually resolves and
 * binds one of these into the container per-request (as an instance —
 * see its class doc); this class is just the value object + the
 * contract Repository is built against.
 */
final class CurrentTenant
{
    public function __construct(
        public readonly int $id,
        public readonly string $name
    ) {
    }
}
