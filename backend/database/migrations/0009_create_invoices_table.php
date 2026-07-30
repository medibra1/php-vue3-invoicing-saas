<?php

declare(strict_types=1);

use App\Core\Database\Connection;
use App\Core\Database\Migration;

/**
 * Minimal invoices table, built ahead of full Invoice CRUD (Phase 4)
 * because QuoteToInvoiceConverter (Phase 3) needs somewhere to land a
 * converted quote. Only what conversion needs is here — PDF generation,
 * payment tracking, etc. are Phase 4 concerns and may still add columns.
 */
return new class implements Migration {
    public function up(Connection $connection): void
    {
        $connection->statement("
            CREATE TABLE invoices (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id BIGINT UNSIGNED NOT NULL,
                client_id BIGINT UNSIGNED NOT NULL,
                quote_id BIGINT UNSIGNED NULL DEFAULT NULL,
                number VARCHAR(50) NOT NULL,
                status ENUM('draft', 'sent', 'paid', 'overdue', 'cancelled') NOT NULL DEFAULT 'draft',
                issue_date DATE NOT NULL,
                due_date DATE NULL DEFAULT NULL,
                notes VARCHAR(1000) NULL DEFAULT NULL,
                total DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP NULL DEFAULT NULL,
                UNIQUE KEY invoices_tenant_id_number_unique (tenant_id, number),
                KEY invoices_tenant_id_id_index (tenant_id, id),
                CONSTRAINT invoices_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id),
                CONSTRAINT invoices_client_id_foreign FOREIGN KEY (client_id) REFERENCES clients (id),
                CONSTRAINT invoices_quote_id_foreign FOREIGN KEY (quote_id) REFERENCES quotes (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    public function down(Connection $connection): void
    {
        $connection->statement('DROP TABLE IF EXISTS invoices');
    }
};
