<?php

declare(strict_types=1);

namespace App\Core\Database;

use PDO;
use PDOStatement;
use Throwable;

/**
 * Thin wrapper around a single PDO connection.
 *
 * Kept deliberately small: its only jobs are (1) opening the connection
 * with sane defaults (exceptions on error, no emulated prepares, assoc
 * fetch mode) and (2) exposing the primitives QueryBuilder and
 * transactional service code need. Query building itself lives in
 * QueryBuilder — this class knows nothing about SQL syntax.
 */
final class Connection
{
    private readonly PDO $pdo;

    public function __construct(string $dsn, string $username, string $password, array $options = [])
    {
        $this->pdo = new PDO($dsn, $username, $password, $options + [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    /**
     * Builds a Connection from environment variables (DB_HOST, DB_PORT,
     * DB_DATABASE, DB_USERNAME, DB_PASSWORD, DB_CHARSET). Reads whatever
     * is already in the environment — loading a .env file into it (in
     * local dev) is a bootstrap concern, not this class's job.
     */
    public static function fromEnv(): self
    {
        $host = self::env('DB_HOST', '127.0.0.1');
        $port = self::env('DB_PORT', '3306');
        $database = self::env('DB_DATABASE', 'invoicepro');
        $charset = self::env('DB_CHARSET', 'utf8mb4');

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

        return new self($dsn, self::env('DB_USERNAME', 'root'), self::env('DB_PASSWORD', ''));
    }

    /** Starts building a query against $table. See QueryBuilder for the fluent API. */
    public function table(string $table): QueryBuilder
    {
        return new QueryBuilder($this, $table);
    }

    /**
     * Prepares and executes a raw SQL statement with positional/named
     * bindings. The building block every QueryBuilder method compiles
     * down to.
     */
    public function statement(string $sql, array $bindings = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);

        return $stmt;
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * Runs $callback inside a transaction, committing on success and
     * rolling back on any exception (which is then rethrown — this
     * never swallows errors, it only guarantees atomicity).
     */
    public function transaction(\Closure $callback): mixed
    {
        $this->pdo->beginTransaction();

        try {
            $result = $callback($this);
            $this->pdo->commit();

            return $result;
        } catch (Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }

    private static function env(string $key, string $default): string
    {
        $value = $_ENV[$key] ?? getenv($key);

        return $value === false || $value === null || $value === '' ? $default : (string) $value;
    }
}
