<?php

declare(strict_types=1);

use App\Core\Database\Connection;
use App\Core\Database\Migration;

/**
 * roles/permissions/role_permissions are global RBAC definitions shared
 * across every tenant (a fixed set — owner/admin/accountant/viewer —
 * not something each tenant customizes), so none of these three tables
 * carry a tenant_id. Only the assignment of a role to a user within a
 * tenant (user_roles, next migration) is tenant-scoped.
 */
return new class implements Migration {
    public function up(Connection $connection): void
    {
        $connection->statement('
            CREATE TABLE roles (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(50) NOT NULL,
                name VARCHAR(100) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY roles_slug_unique (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ');

        $connection->statement('
            CREATE TABLE permissions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(100) NOT NULL,
                description VARCHAR(255) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY permissions_slug_unique (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ');

        $connection->statement('
            CREATE TABLE role_permissions (
                role_id BIGINT UNSIGNED NOT NULL,
                permission_id BIGINT UNSIGNED NOT NULL,
                PRIMARY KEY (role_id, permission_id),
                CONSTRAINT role_permissions_role_id_foreign FOREIGN KEY (role_id) REFERENCES roles (id),
                CONSTRAINT role_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES permissions (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ');
    }

    public function down(Connection $connection): void
    {
        $connection->statement('DROP TABLE IF EXISTS role_permissions');
        $connection->statement('DROP TABLE IF EXISTS permissions');
        $connection->statement('DROP TABLE IF EXISTS roles');
    }
};
