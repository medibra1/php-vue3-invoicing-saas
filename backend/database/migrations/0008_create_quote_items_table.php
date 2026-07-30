<?php

declare(strict_types=1);

use App\Core\Database\Connection;
use App\Core\Database\Migration;

/**
 * No deleted_at: line items have no identity or audit value outside
 * their parent quote, so editing a quote's items replaces the whole
 * set (delete-then-reinsert, see Core\Database\HasLineItems) rather
 * than tracking per-item history. tenant_id is redundant with
 * quotes.tenant_id but kept for the same reason every other
 * tenant-scoped table has it: direct QueryBuilder::forTenant()
 * filtering without a join.
 */
return new class implements Migration {
    public function up(Connection $connection): void
    {
        $connection->statement('
            CREATE TABLE quote_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id BIGINT UNSIGNED NOT NULL,
                quote_id BIGINT UNSIGNED NOT NULL,
                description VARCHAR(500) NOT NULL,
                quantity DECIMAL(12, 2) NOT NULL,
                unit_price DECIMAL(12, 2) NOT NULL,
                line_total DECIMAL(12, 2) NOT NULL,
                sort_order INT UNSIGNED NOT NULL DEFAULT 0,
                KEY quote_items_tenant_id_quote_id_index (tenant_id, quote_id),
                CONSTRAINT quote_items_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id),
                CONSTRAINT quote_items_quote_id_foreign FOREIGN KEY (quote_id) REFERENCES quotes (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ');
    }

    public function down(Connection $connection): void
    {
        $connection->statement('DROP TABLE IF EXISTS quote_items');
    }
};
