<?php

declare(strict_types=1);

use App\Core\Database\Connection;
use App\Core\Database\Migration;

return new class implements Migration {
    public function up(Connection $connection): void
    {
        // Only the SHA-256 hash of the refresh token is stored — the
        // raw token is returned to the client once and never persisted,
        // same principle as a password hash (a DB leak shouldn't hand
        // out usable tokens).
        $connection->statement('
            CREATE TABLE refresh_tokens (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                token_hash CHAR(64) NOT NULL,
                expires_at TIMESTAMP NOT NULL,
                revoked_at TIMESTAMP NULL DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY refresh_tokens_token_hash_unique (token_hash),
                KEY refresh_tokens_tenant_id_id_index (tenant_id, id),
                CONSTRAINT refresh_tokens_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id),
                CONSTRAINT refresh_tokens_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ');
    }

    public function down(Connection $connection): void
    {
        $connection->statement('DROP TABLE IF EXISTS refresh_tokens');
    }
};
