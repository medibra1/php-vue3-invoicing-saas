<?php

declare(strict_types=1);

use App\Core\Database\Connection;
use App\Core\Database\Migration;

return new class implements Migration {
    public function up(Connection $connection): void
    {
        $connection->statement('
            CREATE TABLE clients (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NULL,
                phone VARCHAR(50) NULL,
                address VARCHAR(500) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP NULL DEFAULT NULL,
                KEY clients_tenant_id_id_index (tenant_id, id),
                CONSTRAINT clients_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ');
    }

    public function down(Connection $connection): void
    {
        $connection->statement('DROP TABLE IF EXISTS clients');
    }
};
