<?php

declare(strict_types=1);

use App\Core\Database\Connection;
use App\Core\Database\Migration;

/**
 * One row per payment recorded against an invoice. No paid_amount/balance
 * column on invoices itself — the running balance is always computed live
 * via SUM(payments.amount) (PaymentRepository::sumForInvoice()), the same
 * "derive, don't denormalize" choice made throughout this schema.
 */
return new class implements Migration {
    public function up(Connection $connection): void
    {
        $connection->statement('
            CREATE TABLE payments (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id BIGINT UNSIGNED NOT NULL,
                invoice_id BIGINT UNSIGNED NOT NULL,
                amount DECIMAL(12, 2) NOT NULL,
                method VARCHAR(50) NULL DEFAULT NULL,
                paid_at DATE NOT NULL,
                notes VARCHAR(500) NULL DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP NULL DEFAULT NULL,
                KEY payments_tenant_id_invoice_id_index (tenant_id, invoice_id),
                KEY payments_tenant_id_id_index (tenant_id, id),
                CONSTRAINT payments_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id),
                CONSTRAINT payments_invoice_id_foreign FOREIGN KEY (invoice_id) REFERENCES invoices (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ');
    }

    public function down(Connection $connection): void
    {
        $connection->statement('DROP TABLE IF EXISTS payments');
    }
};
