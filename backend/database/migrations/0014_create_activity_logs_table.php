<?php

declare(strict_types=1);

use App\Core\Database\Connection;
use App\Core\Database\Migration;

/**
 * Append-only audit trail — no `deleted_at`, an activity log entry is
 * never edited or removed, same "accounting data is never destroyed"
 * philosophy as the rest of this schema, taken further since this
 * table's entire purpose is being an immutable record.
 */
return new class implements Migration {
    public function up(Connection $connection): void
    {
        $connection->statement('
            CREATE TABLE activity_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NULL DEFAULT NULL,
                action VARCHAR(100) NOT NULL,
                subject_type VARCHAR(50) NOT NULL,
                subject_id BIGINT UNSIGNED NOT NULL,
                description VARCHAR(500) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY activity_logs_tenant_id_id_index (tenant_id, id),
                CONSTRAINT activity_logs_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id),
                CONSTRAINT activity_logs_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ');
    }

    public function down(Connection $connection): void
    {
        $connection->statement('DROP TABLE IF EXISTS activity_logs');
    }
};
