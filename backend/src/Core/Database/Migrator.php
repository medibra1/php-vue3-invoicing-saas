<?php

declare(strict_types=1);

namespace App\Core\Database;

/**
 * Minimal migration runner: no rollback batching, no fancy CLI — just
 * "run every file in database/migrations/ that hasn't run yet, in
 * filename order, and record it". Filenames are expected to be
 * numerically prefixed (0001_create_tenants_table.php) so filesystem
 * order is also chronological order.
 *
 * Each migration file returns an anonymous class implementing
 * Migration; requiring the file both loads and instantiates it.
 */
final class Migrator
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $migrationsPath
    ) {
    }

    /** @return string[] Names of migrations that were run (empty if already up to date) */
    public function run(): array
    {
        $this->ensureMigrationsTableExists();
        $applied = $this->appliedMigrations();
        $ran = [];

        foreach ($this->migrationFiles() as $file) {
            $name = basename($file, '.php');

            if (in_array($name, $applied, true)) {
                continue;
            }

            /** @var Migration $migration */
            $migration = require $file;
            $migration->up($this->connection);

            $this->connection->table('migrations')->insert(['migration' => $name]);
            $ran[] = $name;
        }

        return $ran;
    }

    private function ensureMigrationsTableExists(): void
    {
        $this->connection->statement('
            CREATE TABLE IF NOT EXISTS migrations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL,
                run_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ');
    }

    /** @return string[] */
    private function appliedMigrations(): array
    {
        return array_column($this->connection->table('migrations')->get(), 'migration');
    }

    /** @return string[] */
    private function migrationFiles(): array
    {
        $files = glob($this->migrationsPath . '/*.php') ?: [];
        sort($files);

        return $files;
    }
}
