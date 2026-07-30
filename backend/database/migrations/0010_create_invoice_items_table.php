<?php

declare(strict_types=1);

use App\Core\Database\Connection;
use App\Core\Database\Migration;

/** Mirrors quote_items exactly — same rationale, see that migration. */
return new class implements Migration {
    public function up(Connection $connection): void
    {
        $connection->statement('
            CREATE TABLE invoice_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id BIGINT UNSIGNED NOT NULL,
                invoice_id BIGINT UNSIGNED NOT NULL,
                description VARCHAR(500) NOT NULL,
                quantity DECIMAL(12, 2) NOT NULL,
                unit_price DECIMAL(12, 2) NOT NULL,
                line_total DECIMAL(12, 2) NOT NULL,
                sort_order INT UNSIGNED NOT NULL DEFAULT 0,
                KEY invoice_items_tenant_id_invoice_id_index (tenant_id, invoice_id),
                CONSTRAINT invoice_items_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id),
                CONSTRAINT invoice_items_invoice_id_foreign FOREIGN KEY (invoice_id) REFERENCES invoices (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ');
    }

    public function down(Connection $connection): void
    {
        $connection->statement('DROP TABLE IF EXISTS invoice_items');
    }
};
