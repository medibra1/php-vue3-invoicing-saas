<?php

declare(strict_types=1);

use App\Core\Database\Connection;
use App\Core\Database\Migration;

/**
 * Carries tenant_id directly (redundant with users.tenant_id, but
 * consistent with the app-wide rule that every tenant-scoped table is
 * queryable via QueryBuilder::forTenant() without a join) — PermissionRepository
 * relies on being able to filter this table by tenant_id directly.
 */
return new class implements Migration {
    public function up(Connection $connection): void
    {
        $connection->statement('
            CREATE TABLE user_roles (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                role_id BIGINT UNSIGNED NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY user_roles_unique (tenant_id, user_id, role_id),
                KEY user_roles_tenant_id_id_index (tenant_id, id),
                CONSTRAINT user_roles_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id),
                CONSTRAINT user_roles_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id),
                CONSTRAINT user_roles_role_id_foreign FOREIGN KEY (role_id) REFERENCES roles (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ');
    }

    public function down(Connection $connection): void
    {
        $connection->statement('DROP TABLE IF EXISTS user_roles');
    }
};
