<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Database\Connection;

/**
 * Seeds the fixed, global RBAC matrix (CLAUDE.md domain: owner/admin/
 * accountant/viewer). Idempotent — safe to run more than once, each
 * permission/role is upserted by its unique slug rather than blindly
 * inserted.
 */
final class RolesAndPermissionsSeeder
{
    /** @var array<int, array{slug: string, description: string}> */
    private const PERMISSIONS = [
        ['slug' => 'clients.view', 'description' => 'View clients'],
        ['slug' => 'clients.create', 'description' => 'Create clients'],
        ['slug' => 'clients.update', 'description' => 'Update clients'],
        ['slug' => 'clients.delete', 'description' => 'Delete clients'],
        ['slug' => 'quotes.view', 'description' => 'View quotes'],
        ['slug' => 'quotes.create', 'description' => 'Create quotes'],
        ['slug' => 'quotes.update', 'description' => 'Update quotes'],
        ['slug' => 'quotes.delete', 'description' => 'Delete quotes'],
        ['slug' => 'quotes.convert', 'description' => 'Convert a quote into an invoice'],
        ['slug' => 'invoices.view', 'description' => 'View invoices'],
        ['slug' => 'invoices.create', 'description' => 'Create invoices'],
        ['slug' => 'invoices.update', 'description' => 'Update invoices'],
        ['slug' => 'invoices.delete', 'description' => 'Delete invoices'],
        ['slug' => 'payments.view', 'description' => 'View payments'],
        ['slug' => 'payments.create', 'description' => 'Record payments'],
        ['slug' => 'stats.view', 'description' => 'View dashboard metrics'],
        ['slug' => 'activity_logs.view', 'description' => 'View the activity log'],
        ['slug' => 'users.manage', 'description' => 'Invite/manage team members'],
    ];

    /** @var array<string, string[]> Role slug => list of permission slugs ('*' = every permission) */
    private const ROLE_PERMISSIONS = [
        'owner' => ['*'],
        'admin' => ['*'],
        'accountant' => [
            'clients.view', 'clients.create', 'clients.update',
            'quotes.view', 'quotes.create', 'quotes.update', 'quotes.convert',
            'invoices.view', 'invoices.create', 'invoices.update',
            'payments.view', 'payments.create', 'stats.view', 'activity_logs.view',
        ],
        'viewer' => [
            'clients.view', 'quotes.view', 'invoices.view', 'payments.view',
            'stats.view', 'activity_logs.view',
        ],
    ];

    private const ROLE_NAMES = [
        'owner' => 'Owner',
        'admin' => 'Admin',
        'accountant' => 'Accountant',
        'viewer' => 'Viewer',
    ];

    public function run(Connection $connection): void
    {
        $permissionIds = $this->seedPermissions($connection);
        $roleIds = $this->seedRoles($connection);

        foreach (self::ROLE_PERMISSIONS as $roleSlug => $permissionSlugs) {
            $slugs = $permissionSlugs === ['*'] ? array_keys($permissionIds) : $permissionSlugs;

            foreach ($slugs as $permissionSlug) {
                $this->attachPermission($connection, $roleIds[$roleSlug], $permissionIds[$permissionSlug]);
            }
        }
    }

    /** @return array<string, int> slug => id */
    private function seedPermissions(Connection $connection): array
    {
        $ids = [];

        foreach (self::PERMISSIONS as $permission) {
            $existing = $connection->table('permissions')->where('slug', '=', $permission['slug'])->first();
            $ids[$permission['slug']] = $existing !== null
                ? (int) $existing['id']
                : (int) $connection->table('permissions')->insert($permission);
        }

        return $ids;
    }

    /** @return array<string, int> slug => id */
    private function seedRoles(Connection $connection): array
    {
        $ids = [];

        foreach (self::ROLE_NAMES as $slug => $name) {
            $existing = $connection->table('roles')->where('slug', '=', $slug)->first();
            $ids[$slug] = $existing !== null
                ? (int) $existing['id']
                : (int) $connection->table('roles')->insert(['slug' => $slug, 'name' => $name]);
        }

        return $ids;
    }

    private function attachPermission(Connection $connection, int $roleId, int $permissionId): void
    {
        $existing = $connection->table('role_permissions')
            ->where('role_id', '=', $roleId)
            ->where('permission_id', '=', $permissionId)
            ->first();

        if ($existing === null) {
            $connection->table('role_permissions')->insert(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }
    }
}
