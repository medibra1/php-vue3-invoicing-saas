<?php

declare(strict_types=1);

use App\Core\Database\Connection;
use App\Core\Database\Migration;

/**
 * A quote's `number` is assigned at creation (including drafts), not
 * only when first sent — avoids a nullable-then-unique column, at the
 * cost of "burning" a number if a draft is later deleted without ever
 * being sent. Acceptable: numbers are cheap, gaps are normal in real
 * invoicing systems too.
 */
return new class implements Migration {
    public function up(Connection $connection): void
    {
        $connection->statement("
            CREATE TABLE quotes (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id BIGINT UNSIGNED NOT NULL,
                client_id BIGINT UNSIGNED NOT NULL,
                number VARCHAR(50) NOT NULL,
                status ENUM('draft', 'sent', 'accepted', 'rejected', 'expired') NOT NULL DEFAULT 'draft',
                issue_date DATE NOT NULL,
                expiry_date DATE NULL DEFAULT NULL,
                notes VARCHAR(1000) NULL DEFAULT NULL,
                total DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP NULL DEFAULT NULL,
                UNIQUE KEY quotes_tenant_id_number_unique (tenant_id, number),
                KEY quotes_tenant_id_id_index (tenant_id, id),
                CONSTRAINT quotes_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id),
                CONSTRAINT quotes_client_id_foreign FOREIGN KEY (client_id) REFERENCES clients (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    public function down(Connection $connection): void
    {
        $connection->statement('DROP TABLE IF EXISTS quotes');
    }
};
