<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Core\Database\Connection;
use App\Core\Database\CurrentTenant;

/**
 * Checks whether a user holds a given RBAC permission, within the
 * current tenant — a user's roles are assigned per tenant (`user_roles`,
 * CLAUDE.md domain schema), while `roles`/`permissions`/
 * `role_permissions` are global RBAC definitions shared across tenants.
 * Only the `user_roles` lookup is tenant-scoped as a result.
 *
 * Three small queries rather than a single JOIN: QueryBuilder doesn't
 * support joins yet (nothing else has needed one so far) — worth
 * collapsing into one query once it does.
 */
final class PermissionRepository
{
    public function __construct(
        private readonly Connection $connection,
        private readonly CurrentTenant $tenant
    ) {
    }

    public function userHasPermission(int $userId, string $permissionSlug): bool
    {
        $roleIds = array_column(
            $this->connection->table('user_roles')
                ->where('user_id', '=', $userId)
                ->where('tenant_id', '=', $this->tenant->id)
                ->get(),
            'role_id'
        );

        if ($roleIds === []) {
            return false;
        }

        $permission = $this->connection->table('permissions')
            ->where('slug', '=', $permissionSlug)
            ->first();

        if ($permission === null) {
            return false;
        }

        return $this->connection->table('role_permissions')
            ->whereIn('role_id', $roleIds)
            ->where('permission_id', '=', $permission['id'])
            ->count() > 0;
    }
}
