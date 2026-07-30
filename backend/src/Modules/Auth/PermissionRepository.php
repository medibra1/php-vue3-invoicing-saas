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

    /**
     * Used by Profile::show() to surface a display-only role name — not
     * a permission check, so it doesn't belong in userHasPermission(),
     * but it's the same tenant-scoped user_roles lookup this class
     * already owns.
     *
     * @return string[] Role slugs assigned to this user within the current tenant.
     */
    public function roleSlugsForUser(int $userId): array
    {
        $roleIds = array_column(
            $this->connection->table('user_roles')
                ->where('user_id', '=', $userId)
                ->where('tenant_id', '=', $this->tenant->id)
                ->get(),
            'role_id'
        );

        if ($roleIds === []) {
            return [];
        }

        return array_column($this->connection->table('roles')->whereIn('id', $roleIds)->get(), 'slug');
    }
}
