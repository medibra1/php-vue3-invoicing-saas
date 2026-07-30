<?php

declare(strict_types=1);

use App\Core\Database\Connection;
use App\Core\Database\Migration;

/**
 * Adds 'partially_paid' between 'sent' and 'paid' — Phase 5 payment
 * recording supports partial payments against an invoice (CLAUDE.md
 * "Payment ↔ invoice status" decision), so the balance needs a status of
 * its own between "nothing paid yet" and "fully paid".
 */
return new class implements Migration {
    public function up(Connection $connection): void
    {
        $connection->statement("
            ALTER TABLE invoices
            MODIFY status ENUM('draft', 'sent', 'partially_paid', 'paid', 'overdue', 'cancelled') NOT NULL DEFAULT 'draft'
        ");
    }

    public function down(Connection $connection): void
    {
        $connection->statement("
            ALTER TABLE invoices
            MODIFY status ENUM('draft', 'sent', 'paid', 'overdue', 'cancelled') NOT NULL DEFAULT 'draft'
        ");
    }
};
