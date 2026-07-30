<?php

declare(strict_types=1);

use App\Core\Database\Connection;
use App\Core\Database\Migration;

/**
 * Phase 6 Profile module. Stores a path relative to
 * public/uploads/avatars/, not a full URL — AvatarService builds the
 * public URL on read, so the base URL can change (env, domain) without
 * a data migration.
 */
return new class implements Migration {
    public function up(Connection $connection): void
    {
        $connection->statement('ALTER TABLE users ADD COLUMN avatar_path VARCHAR(255) NULL DEFAULT NULL AFTER password_hash');
    }

    public function down(Connection $connection): void
    {
        $connection->statement('ALTER TABLE users DROP COLUMN avatar_path');
    }
};
