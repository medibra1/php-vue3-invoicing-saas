<?php

declare(strict_types=1);

namespace App\Core\Database;

/**
 * Fluent SQL query builder on top of Connection/PDO.
 *
 * Two safety mechanisms baked in, both non-optional once triggered:
 *
 * - **Tenant scoping**: calling {@see forTenant()} once appends a
 *   `tenant_id = ?` predicate that every terminal method (get/first/
 *   count/update/delete) then relies on, and makes insert() inject the
 *   tenant_id column automatically. A future base Repository will call
 *   forTenant() with the request's current tenant on every query it
 *   builds, so a module Repository (ClientRepository, InvoiceRepository...)
 *   cannot forget to filter by tenant — it's structurally automatic
 *   rather than something to remember at each call site. Tables that
 *   aren't tenant-scoped (tenants, roles, permissions...) simply never
 *   call forTenant().
 * - **No blind mutations**: update()/delete() refuse to run without at
 *   least one WHERE clause (see UnsafeQueryException), so a missing
 *   ->where(...) fails loudly instead of touching every row.
 *
 * All values are bound as PDO parameters, never interpolated into the
 * SQL string — only column/table names and the ORDER BY direction
 * (validated against an allow-list) are.
 */
final class QueryBuilder
{
    /** @var string[] */
    private array $columns = ['*'];

    /** @var array<int, array{column: string, operator: string, value: mixed}> */
    private array $wheres = [];

    private ?string $orderColumn = null;
    private string $orderDirection = 'ASC';
    private ?int $limitValue = null;
    private ?int $offsetValue = null;

    private bool $tenantScoped = false;
    private ?int $tenantId = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly string $table
    ) {
    }

    public function select(string ...$columns): static
    {
        $this->columns = $columns === [] ? ['*'] : $columns;

        return $this;
    }

    public function where(string $column, string $operator, mixed $value): static
    {
        $this->wheres[] = ['column' => $column, 'operator' => $operator, 'value' => $value];

        return $this;
    }

    /** @param array<int, mixed> $values */
    public function whereIn(string $column, array $values): static
    {
        $this->wheres[] = ['column' => $column, 'operator' => 'IN', 'value' => $values];

        return $this;
    }

    /**
     * `column LIKE %value%`, still fully parameterized (the whole
     * `%value%` string is one bound value, same placeholder path as a
     * normal where()). The search term's own `%`/`_` aren't escaped —
     * a user typing a literal percent sign gets SQL wildcard behavior
     * instead of a literal match, an acceptable simplification for a
     * search box (not a SQL injection concern either way).
     */
    public function whereLike(string $column, string $value): static
    {
        return $this->where($column, 'LIKE', "%{$value}%");
    }

    /**
     * `column = ?` bound to NULL is always false in SQL (three-valued
     * logic) — it never matches, even for actually-NULL rows. These two
     * methods exist so soft-delete checks (`deleted_at IS NULL`) can be
     * expressed correctly instead of silently matching zero rows.
     */
    public function whereNull(string $column): static
    {
        $this->wheres[] = ['column' => $column, 'operator' => 'IS NULL', 'value' => null];

        return $this;
    }

    public function whereNotNull(string $column): static
    {
        $this->wheres[] = ['column' => $column, 'operator' => 'IS NOT NULL', 'value' => null];

        return $this;
    }

    /**
     * Scopes every subsequent operation on this builder to a single
     * tenant. See the class-level doc — this is the mechanism the
     * multi-tenancy security requirement (CLAUDE.md) relies on.
     */
    public function forTenant(int $tenantId): static
    {
        $this->tenantScoped = true;
        $this->tenantId = $tenantId;

        return $this->where('tenant_id', '=', $tenantId);
    }

    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $direction = strtoupper($direction);

        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new \InvalidArgumentException("Invalid ORDER BY direction [{$direction}].");
        }

        $this->orderColumn = $column;
        $this->orderDirection = $direction;

        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limitValue = $limit;

        return $this;
    }

    public function offset(int $offset): static
    {
        $this->offsetValue = $offset;

        return $this;
    }

    /** @return array<int, array<string, mixed>> */
    public function get(): array
    {
        [$sql, $bindings] = $this->compileSelect();

        return $this->connection->statement($sql, $bindings)->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function first(): ?array
    {
        $row = $this->limit(1)->get();

        return $row[0] ?? null;
    }

    public function count(): int
    {
        $originalColumns = $this->columns;
        $this->columns = ['COUNT(*) AS aggregate'];

        [$sql, $bindings] = $this->compileSelect();
        $row = $this->connection->statement($sql, $bindings)->fetch();

        $this->columns = $originalColumns;

        return (int) ($row['aggregate'] ?? 0);
    }

    /**
     * @param array<string, mixed> $data
     * @return string The last insert ID.
     */
    public function insert(array $data): string
    {
        if ($this->tenantScoped) {
            // Always enforced, never trusted from caller input — a
            // Repository could otherwise "insert" into another tenant.
            $data['tenant_id'] = $this->tenantId;
        }

        $columns = array_keys($data);
        $placeholders = array_map(static fn (string $c): string => ":{$c}", $columns);

        $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ') '
            . 'VALUES (' . implode(', ', $placeholders) . ')';

        $bindings = [];
        foreach ($data as $column => $value) {
            $bindings[":{$column}"] = $value;
        }

        $this->connection->statement($sql, $bindings);

        return $this->connection->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     * @throws UnsafeQueryException if no WHERE clause was set.
     */
    public function update(array $data): int
    {
        $this->assertHasWhereClause('update');

        $setClauses = [];
        $bindings = [];

        foreach ($data as $column => $value) {
            $setClauses[] = "{$column} = :set_{$column}";
            $bindings[":set_{$column}"] = $value;
        }

        [$whereSql, $whereBindings] = $this->compileWheres();

        $sql = "UPDATE {$this->table} SET " . implode(', ', $setClauses) . $whereSql;

        return $this->connection->statement($sql, array_merge($bindings, $whereBindings))->rowCount();
    }

    /**
     * Raw hard delete. Business entities use soft deletes instead
     * (`update(['deleted_at' => ...])`, see CLAUDE.md) — this remains a
     * real DELETE for tables that genuinely need one.
     *
     * @throws UnsafeQueryException if no WHERE clause was set.
     */
    public function delete(): int
    {
        $this->assertHasWhereClause('delete');

        [$whereSql, $bindings] = $this->compileWheres();

        return $this->connection->statement("DELETE FROM {$this->table}{$whereSql}", $bindings)->rowCount();
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    private function compileSelect(): array
    {
        [$whereSql, $bindings] = $this->compileWheres();

        $sql = 'SELECT ' . implode(', ', $this->columns) . " FROM {$this->table}{$whereSql}";

        if ($this->orderColumn !== null) {
            $sql .= " ORDER BY {$this->orderColumn} {$this->orderDirection}";
        }

        if ($this->limitValue !== null) {
            $sql .= " LIMIT {$this->limitValue}";
        }

        if ($this->offsetValue !== null) {
            $sql .= " OFFSET {$this->offsetValue}";
        }

        return [$sql, $bindings];
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    private function compileWheres(): array
    {
        if ($this->wheres === []) {
            return ['', []];
        }

        $clauses = [];
        $bindings = [];

        foreach ($this->wheres as $i => $where) {
            if ($where['operator'] === 'IN') {
                $placeholders = [];

                foreach (array_values($where['value']) as $j => $value) {
                    $placeholder = ":where_{$i}_{$j}";
                    $placeholders[] = $placeholder;
                    $bindings[$placeholder] = $value;
                }

                $clauses[] = "{$where['column']} IN (" . implode(', ', $placeholders) . ')';
                continue;
            }

            if ($where['operator'] === 'IS NULL' || $where['operator'] === 'IS NOT NULL') {
                $clauses[] = "{$where['column']} {$where['operator']}";
                continue;
            }

            $placeholder = ":where_{$i}";
            $clauses[] = "{$where['column']} {$where['operator']} {$placeholder}";
            $bindings[$placeholder] = $where['value'];
        }

        return [' WHERE ' . implode(' AND ', $clauses), $bindings];
    }

    private function assertHasWhereClause(string $operation): void
    {
        if ($this->wheres === []) {
            throw new UnsafeQueryException(
                "Refusing to run {$operation}() on [{$this->table}] without a WHERE clause "
                . '— this would affect every row in the table.'
            );
        }
    }
}
