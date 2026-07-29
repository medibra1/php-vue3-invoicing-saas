<?php

declare(strict_types=1);

use App\Core\Database\Connection;
use App\Core\Database\Migration;

return new class implements Migration {
    public function up(Connection $connection): void
    {
        // email is globally unique, not tenant-scoped: login only takes
        // an email+password (no tenant selection step), so the same
        // email can't be reused across two tenants. This is the one
        // deliberate exception to the "tenant-scoped uniqueness" rule
        // (that rule is about business data like invoice numbers).
        $connection->statement('
            CREATE TABLE users (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP NULL DEFAULT NULL,
                UNIQUE KEY users_email_unique (email),
                KEY users_tenant_id_id_index (tenant_id, id),
                CONSTRAINT users_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ');
    }

    public function down(Connection $connection): void
    {
        $connection->statement('DROP TABLE IF EXISTS users');
    }
};
