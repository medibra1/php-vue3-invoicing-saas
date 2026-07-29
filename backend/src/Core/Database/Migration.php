<?php

declare(strict_types=1);

namespace App\Core\Database;

/**
 * Contract for a single migration file under database/migrations/.
 * Each file returns an anonymous class implementing this interface —
 * see Migrator for how they're discovered and run.
 */
interface Migration
{
    public function up(Connection $connection): void;

    public function down(Connection $connection): void;
}
